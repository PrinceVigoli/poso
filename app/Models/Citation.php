<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Citation extends Model
{
    protected $fillable = [
        'violation_id', 'ticket_no', 'fine_amount',
        'due_date', 'payment_status', 'paid_at',
        'treasury_receipt_no', 'verified_by', 'verified_at',
        'treasury_receipt_id', 'verification_version', 'receipt_date',
    ];

    protected $casts = ['receipt_date' => 'date', 'due_date' => 'date', 'paid_at' => 'date', 'verified_at' => 'datetime', 'verification_version' => 'integer'];

    public function violation()
    {
        return $this->belongsTo(Violation::class);
    }

    public function treasuryReceipt() { return $this->belongsTo(TreasuryReceipt::class); }

    public function scopePayable($query)
    {
        return $query->whereHas('violation', fn ($q) => $q->payable());
    }

    public function getFineLabelAttribute(): string
    {
        return $this->fine_amount === null ? 'Not configured' : "\u{20B1}".number_format($this->fine_amount, 2);
    }

    public function scopeUnverified($query)
    {
        return $query->whereIn('payment_status', ['pending', 'overdue']);
    }

    public function scopeOverdue($query)
    {
        return $query->payable()->unverified()->where('due_date', '<', today()->toDateString());
    }

    // Settlements an admin still has to act on: past the due date without a
    // verified receipt, or blocked because the ordinance fine was never
    // configured. A record can be both; callers treat a missing fine as the
    // more specific case, since verification cannot proceed without it.
    public function scopeNeedsAttention($query)
    {
        return $query->payable()->unverified()->where(fn ($q) => $q
            ->whereNull('fine_amount')
            ->orWhere('due_date', '<', today()->toDateString()));
    }

    public function getEffectivePaymentStatusAttribute(): string
    {
        if ($this->payment_status === 'paid') { return 'paid'; }
        return $this->due_date?->lt(today()) ? 'overdue' : 'pending';
    }

    public static function generateTicketNo(): string
    {
        return 'REC-' . \Illuminate\Support\Str::uuid();
    }
}
