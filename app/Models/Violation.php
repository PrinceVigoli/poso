<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Violation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'violator_id', 'officer_id', 'violation_type_id',
        'violation_date', 'location', 'status', 'remarks', 'confiscated_id',
        'person_snapshot', 'snapshot_source', 'public_access_hash', 'submission_token', 'minor_photos',
    ];

    protected $casts = ['violation_date' => 'date', 'person_snapshot' => 'array', 'minor_photos' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $violation) {
            if ($violation->person_snapshot === null) {
                $violation->person_snapshot = Violator::withTrashed()->find($violation->violator_id)?->only(['full_name', 'address', 'vehicle_plate', 'license_no', 'vehicle_type', 'contact_no', 'birthdate']) ?? [];
            }
        });
    }

    public function personDetail(string $field): ?string
    {
        return $this->person_snapshot[$field] ?? null;
    }

    public function getPaymentLabelAttribute(): string
    {
        if ($this->status === 'dismissed') { return 'Dismissed'; }
        return $this->citation?->payment_status === 'paid' ? 'Settled' : 'Not Yet Verified';
    }

    public function getReportPaymentLabelAttribute(): string
    {
        if ($this->status === 'dismissed') { return 'Dismissed'; }
        return $this->citation?->payment_status === 'paid' ? 'Paid' : 'Unpaid';
    }

    public function scopePayable($query)
    {
        return $query->where('status', '!=', 'dismissed')->whereHas('violator');
    }

    public function paymentEvents() { return $this->hasMany(PaymentEvent::class); }

    public function violator()      { return $this->belongsTo(Violator::class); }
    public function officer()       { return $this->belongsTo(User::class, 'officer_id'); }
    public function violationType() { return $this->belongsTo(ViolationType::class)->withTrashed(); }
    public function citation()      { return $this->hasOne(Citation::class); }
}
