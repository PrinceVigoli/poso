<?php
namespace App\Http\Controllers;

use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // The report screen carries its own period selector and date picker, so a
    // separate page offering three links to it was an extra click for nothing.
    // Kept as a redirect so existing links and bookmarks still resolve.
    public function index() { return redirect()->route('reports.period'); }

    // Preserve old links, but use the same restricted daily/weekly/monthly report.
    public function violations(Request $request) { return $this->period($request); }
    public function summary(Request $request) { return $this->period($request); }

    public function period(Request $request)
    {
        $data = $request->validate(['period' => 'nullable|in:daily,weekly,monthly', 'date' => 'nullable|date_format:Y-m-d', 'download' => 'nullable|in:csv']);
        $period = $data['period'] ?? 'daily';
        $date = $data['date'] ?? today()->toDateString();
        $anchor = Carbon::parse($date);
        $start = match ($period) {
            'weekly' => $anchor->copy()->startOfWeek(Carbon::MONDAY),
            'monthly' => $anchor->copy()->startOfMonth(),
            default => $anchor->copy()->startOfDay(),
        };
        $end = match ($period) {
            'weekly' => $start->copy()->addDays(6)->endOfDay(),
            'monthly' => $anchor->copy()->endOfMonth(),
            default => $anchor->copy()->endOfDay(),
        };
        $dateFrom = $start->toDateString();
        $dateTo = $end->toDateString();
        $exclusiveEnd = $end->copy()->addDay()->toDateString();
        $recordsQuery = Violation::with(['violationType', 'citation'])
            ->where('violation_date', '>=', $dateFrom)->where('violation_date', '<', $exclusiveEnd);
        $settlementsQuery = Violation::payable()->with(['violationType', 'citation'])
            ->whereHas('citation', fn ($q) => $q->where('payment_status', 'paid')->where('paid_at', '>=', $dateFrom)->where('paid_at', '<', $exclusiveEnd));
        $eventsQuery = \App\Models\PaymentEvent::with('user')->whereBetween('created_at', [$start, $end]);
        if (($data['download'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($recordsQuery, $settlementsQuery, $eventsQuery) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                $write = function (array $row) use ($out) {
                    $safe = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value, $row);
                    fputcsv($out, $safe, ',', '"', '');
                };
                $write(['Section', 'Record', 'Name', 'Address', 'Offense', 'Apprehension date', 'Current settlement status', 'POSO verification date', 'Treasury receipt', 'Treasury receipt date', 'Fine', 'Action', 'Admin', 'Reason', 'Event time']);
                foreach (['Apprehension' => $recordsQuery, 'Settlement' => $settlementsQuery] as $section => $query) {
                    foreach ($query->lazyById(200) as $v) {
                        $write([$section, $v->id, $v->personDetail('full_name'), $v->personDetail('address'), $v->violationType?->offense_name,
                            $v->violation_date->toDateString(), $v->payment_label, $v->citation?->paid_at?->toDateString(),
                            $v->citation?->treasury_receipt_no, $v->citation?->receipt_date?->toDateString(), $v->citation?->fine_amount]);
                    }
                }
                foreach ($eventsQuery->lazyById(200) as $event) {
                    $write(['Verification change', $event->violation_id, '', '', '', '', '', '',
                        $event->after_state['treasury_receipt_no'] ?? $event->before_state['treasury_receipt_no'] ?? '',
                        $event->after_state['receipt_date'] ?? $event->before_state['receipt_date'] ?? '',
                        $event->after_state['fine_amount'] ?? '', $event->action, $event->user?->name, $event->reason, $event->created_at->toDateTimeString()]);
                }
                fclose($out);
            }, "poso-{$period}-{$dateFrom}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
        }
        $records = $recordsQuery->orderBy('violation_date')->orderBy('id')->paginate(50, ['*'], 'records_page')->withQueryString();
        $settlements = $settlementsQuery->orderBy('id')->paginate(50, ['*'], 'settlements_page')->withQueryString();
        $paymentEvents = $eventsQuery->orderBy('id')->paginate(50, ['*'], 'events_page')->withQueryString();
        return view('reports.period', compact('period', 'date', 'dateFrom', 'dateTo', 'records', 'settlements', 'paymentEvents'));
    }
}
