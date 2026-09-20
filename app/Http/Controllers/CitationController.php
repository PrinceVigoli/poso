<?php

namespace App\Http\Controllers;

use App\Models\{Citation, AuditLog, Violation, TreasuryReceipt, PaymentEvent};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CitationController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('violations.index', array_filter(['search' => $request->input('search'), 'payment_status' => $request->input('status')]));
    }

    public function show(Citation $citation)
    {
        return redirect()->route('violations.show', $citation->violation()->firstOrFail());
    }

    public function verifyPayment(Request $request, Violation $violation)
    {
        return $this->markPaid($request, $violation->citation()->firstOrFail());
    }

    public function markPaid(Request $request, Citation $citation)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'treasury_receipt_no' => 'required|string|max:100',
            'receipt_total' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'receipt_verified' => 'accepted',
            'receipt_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reuse_receipt' => 'nullable|boolean',
        ]);
        $key = TreasuryReceipt::key($data['treasury_receipt_no']);
        if ($key === '') {
            throw ValidationException::withMessages(['treasury_receipt_no' => 'Enter the Treasury receipt number.']);
        }
        DB::transaction(function () use ($citation, $data, $request, $key) {
            $violation = Violation::lockForUpdate()->findOrFail($citation->violation_id);
            $locked = Citation::whereKey($citation->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status === 'paid') {
                // A repeated submit of the same receipt is a double-click, so
                // stay idempotent. A different receipt number means the admin
                // believes something was recorded that was not.
                if (TreasuryReceipt::key($locked->treasury_receipt_no ?? '') === $key) { return; }
                throw ValidationException::withMessages(['treasury_receipt_no' =>
                    'This violation is already verified as settled under receipt '.$locked->treasury_receipt_no.'. Reverse that verification first if it was recorded in error.']);
            }
            if ($violation->status === 'dismissed') {
                throw ValidationException::withMessages(['receipt_verified' => 'Dismissed violations cannot be settled by payment.']);
            }
            if ($locked->fine_amount === null) {
                throw ValidationException::withMessages(['receipt_total' => 'The fine is not configured for this record. Reconcile the ordinance amount before verifying payment.']);
            }
            // The unique reference serializes concurrent claims to the same
            // receipt. Lock order is always violation, payment, then receipt.
            TreasuryReceipt::query()->insertOrIgnore([
                'reference_key' => $key, 'receipt_no' => trim($data['treasury_receipt_no']),
                'violator_id' => $violation->violator_id, 'total_amount' => $data['receipt_total'],
                'receipt_date' => $data['receipt_date'], 'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $receipt = TreasuryReceipt::where('reference_key', $key)->lockForUpdate()->firstOrFail();
            $allocations = $receipt->allocations()->where('payment_status', 'paid')->with('violation')->get();
            if ($allocations->contains(fn ($p) => !$p->violation || $p->violation->violator_id !== $violation->violator_id)) {
                throw ValidationException::withMessages(['treasury_receipt_no' => 'This receipt is already assigned to another person. Review or reverse the incorrect verification first.']);
            }
            if ($allocations->isNotEmpty() && (!config('portals.allow_shared_receipts') || !$request->boolean('reuse_receipt'))) {
                $references = $allocations->map(fn ($p) => '#'.$p->violation_id)->implode(', ');
                throw ValidationException::withMessages(['treasury_receipt_no' => "This receipt is already used on violation(s) {$references}. Confirm shared coverage only after reviewing the receipt."]);
            }
            if ($allocations->contains(fn ($p) => $p->fine_amount === null)) {
                throw ValidationException::withMessages(['receipt_total' => 'An existing allocation has an unknown amount. Reconcile it before sharing this receipt.']);
            }
            if ($allocations->isNotEmpty() && $receipt->receipt_date && $receipt->receipt_date->toDateString() !== $data['receipt_date']) {
                throw ValidationException::withMessages(['receipt_date' => 'The date differs from the previously verified Treasury receipt. Review its allocations.']);
            }
            $total = $this->cents($data['receipt_total']);
            if ($allocations->isNotEmpty() && $receipt->total_amount !== null && $this->cents($receipt->total_amount) !== $total) {
                throw ValidationException::withMessages(['receipt_total' => 'The total differs from the previously verified receipt total. Review the existing allocations.']);
            }
            $allocated = $allocations->sum(fn ($p) => $this->cents($p->fine_amount));
            if ($allocated + $this->cents($locked->fine_amount) > $total) {
                throw ValidationException::withMessages(['receipt_total' => 'The receipt does not have enough unallocated value to cover this violation.']);
            }
            $receipt->update(['violator_id' => $violation->violator_id, 'total_amount' => $data['receipt_total'], 'receipt_date' => $data['receipt_date'], 'needs_review' => false]);
            $before = $this->state($locked);
            $locked->update([
                'payment_status' => 'paid', 'paid_at' => today(), 'receipt_date' => $data['receipt_date'], 'treasury_receipt_no' => $receipt->receipt_no,
                'treasury_receipt_id' => $receipt->id, 'verified_by' => $request->user()->id, 'verified_at' => now(),
                'verification_version' => $locked->verification_version + 1,
            ]);
            $violation->update(['status' => 'settled']);
            $this->event($violation, $locked, 'verified', $before, null);
        }, 3);
        return back()->with('success', 'Treasury payment verified. Violation marked as settled.');
    }

    public function reversePayment(Request $request, Violation $violation)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000', 'verification_version' => 'required|integer|min:0', 'reverse_confirmed' => 'accepted']);
        DB::transaction(function () use ($violation, $data) {
            $violation = Violation::lockForUpdate()->findOrFail($violation->id);
            $payment = $violation->citation()->lockForUpdate()->firstOrFail();
            if ($payment->payment_status !== 'paid' || $payment->verification_version !== (int) $data['verification_version']) {
                throw ValidationException::withMessages(['reason' => 'This payment has changed. Reload the record before correcting it.']);
            }
            if ($payment->treasury_receipt_id) {
                TreasuryReceipt::whereKey($payment->treasury_receipt_id)->lockForUpdate()->firstOrFail();
            }
            $before = $this->state($payment);
            $payment->update([
                'payment_status' => $payment->due_date->lt(today()) ? 'overdue' : 'pending',
                'paid_at' => null, 'receipt_date' => null, 'treasury_receipt_no' => null, 'treasury_receipt_id' => null, 'verified_by' => null, 'verified_at' => null,
                'verification_version' => $payment->verification_version + 1,
            ]);
            if ($violation->status !== 'dismissed') { $violation->update(['status' => 'pending']); }
            $this->event($violation, $payment, 'reversed', $before, $data['reason']);
        }, 3);
        return back()->with('success', 'Verification reversed with an audit record. You can now verify the correct Treasury receipt.');
    }

    public function reconcileFine(Request $request, Violation $violation)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'fine_amount' => 'required|numeric|decimal:0,2|min:0|max:999999.99',
            'ordinance_reference' => 'required|string|max:255',
            'reason' => 'required|string|min:10|max:1000',
            'verification_version' => 'required|integer|min:0',
            'fine_confirmed' => 'accepted',
        ]);
        DB::transaction(function () use ($violation, $data) {
            $violation = Violation::lockForUpdate()->findOrFail($violation->id);
            $payment = $violation->citation()->lockForUpdate()->firstOrFail();
            if ($violation->status === 'dismissed' || $payment->payment_status === 'paid' || $payment->fine_amount !== null
                || $payment->verification_version !== (int) $data['verification_version']) {
                throw ValidationException::withMessages(['fine_amount' => 'This record cannot be reconciled or has changed. Reload it before continuing.']);
            }
            $before = $this->state($payment);
            $payment->update(['fine_amount' => $data['fine_amount'], 'verification_version' => $payment->verification_version + 1]);
            $this->event($violation, $payment, 'fine_configured', $before, $data['ordinance_reference'].' — '.$data['reason']);
        }, 3);
        return back()->with('success', 'Fine configured with an audit record. You can now verify the Treasury receipt.');
    }

    public function receiptDetails(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['receipt_no' => 'required|string|max:100', 'violation_id' => 'required|integer']);
        $violation = Violation::findOrFail($data['violation_id']);
        $receipt = TreasuryReceipt::where('reference_key', TreasuryReceipt::key($data['receipt_no']))->first();
        $allocations = $receipt?->allocations()->where('payment_status', 'paid')->with('violation')->get() ?? collect();
        $allocated = $allocations->sum(fn ($p) => $this->cents($p->fine_amount));
        return response()->json([
            'found' => (bool) $receipt,
            'total' => $receipt?->total_amount,
            'receipt_date' => $receipt?->receipt_date?->toDateString(),
            'allocated' => $allocated / 100,
            'remaining' => $receipt?->total_amount === null ? null : ($this->cents($receipt->total_amount) - $allocated) / 100,
            'same_person' => !$allocations->contains(fn ($p) => !$p->violation || $p->violation->violator_id !== $violation->violator_id),
            'unknown_amount' => $allocations->contains(fn ($p) => $p->fine_amount === null),
            'allocations' => $allocations->map(fn ($p) => ['reference' => $p->violation_id, 'amount' => $p->fine_amount])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function cents($amount): int { return (int) round((float) $amount * 100); }

    private function state(Citation $payment): array
    {
        return $payment->only(['payment_status', 'fine_amount', 'paid_at', 'receipt_date', 'treasury_receipt_no', 'treasury_receipt_id', 'verified_by', 'verified_at', 'verification_version']);
    }

    private function event(Violation $violation, Citation $payment, string $action, array $before, ?string $reason): void
    {
        $after = $this->state($payment);
        PaymentEvent::create(['violation_id' => $violation->id, 'user_id' => auth()->id(), 'action' => $action, 'before_state' => $before, 'after_state' => $after, 'reason' => $reason]);
        AuditLog::record('updated', 'citations', json_encode(['violation_id' => $violation->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason], JSON_UNESCAPED_UNICODE));
    }
}