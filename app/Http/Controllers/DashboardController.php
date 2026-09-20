<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Violator;
use App\Models\Citation;
use App\Models\ViolationType;

class DashboardController extends Controller
{
    public function index()
    {
        // Fix: removed unused DB import, fixed fw-600 class usage
        $stats = [
            'total_violations'   => Violation::count(),
            'pending_violations' => Violation::where('status', 'pending')->count(),
            'settled_violations' => Violation::where('status', 'settled')->count(),
            'total_violators'    => Violator::count(),
            'active_violators'   => Violator::active()->count(),
            'archived_violators' => Violator::archived()->count(),
            'repeat_offenders'   => Violator::has('countedViolations', '>=', Violator::repeatOffenderThreshold())->count(),
            'pending_citations'  => Citation::payable()->whereIn('payment_status', ['pending', 'overdue'])->count(),
            'overdue_citations'  => Citation::overdue()->count(),
            'needs_review' => Citation::payable()->unverified()->whereNull('fine_amount')->count(),
            'paid_records'       => Citation::payable()->where('payment_status', 'paid')->count(),
        ];

        // Violations in the last 7 days grouped by date
        $weekly = Violation::selectRaw('DATE(violation_date) as date, COUNT(*) as count')
            ->where('violation_date', '>=', now()->subDays(6)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $weekly = collect(range(6, 0))->mapWithKeys(function ($daysAgo) use ($weekly) {
            $date = today()->subDays($daysAgo)->toDateString();
            return [$date => $weekly->get($date, 0)];
        });

        // Top 5 offense types
        $topOffenses = \Illuminate\Support\Facades\DB::table('violation_types as t')
            ->join('violations as v', 'v.violation_type_id', '=', 't.id')
            ->whereNull('v.deleted_at')->where('v.status', '!=', 'dismissed')
            ->selectRaw('t.offense_key, MAX(t.id) as latest_type_id, COUNT(*) as violations_count')
            ->groupBy('t.offense_key')->orderByDesc('violations_count')->limit(5)->get();
        $labels = ViolationType::withTrashed()->whereIn('id', $topOffenses->pluck('latest_type_id'))->pluck('offense_name', 'id');
        $topOffenses->each(function ($row) use ($labels) { $row->offense_name = $labels[$row->latest_type_id]; });

        // Recent violations
        $recentViolations = Violation::with(['violator', 'violationType', 'officer', 'citation'])
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('stats', 'weekly', 'topOffenses', 'recentViolations'));
    }
}
