<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Violator extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name', 'license_no', 'vehicle_plate',
        'vehicle_type', 'address', 'contact_no', 'birthdate',
    ];

    // Fix: added birthdate cast
    protected $casts = [
        'birthdate' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $person) {
            $person->normalized_name = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::squish($person->full_name));
            $person->normalized_license = self::squashReference($person->license_no);
            $person->normalized_plate = self::squashReference($person->vehicle_plate);
        });
    }

    /**
     * Licence and plate numbers are written inconsistently, so compare them
     * without spaces, hyphens or case. Returns null when there is nothing to
     * match, which keeps blank values from colliding with one another.
     */
    public static function squashReference(?string $value): ?string
    {
        $squashed = strtoupper(preg_replace('/[\s-]+/', '', (string) $value));

        return $squashed === '' ? null : $squashed;
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }

    /** Violations that count toward the repeat-offender rule. */
    public function countedViolations()
    {
        return $this->hasMany(Violation::class)->where('status', '!=', 'dismissed');
    }

    public function unpaidViolations()
    {
        return $this->violations()->where('status', '!=', 'dismissed')->whereDoesntHave('citation', fn ($query) =>
            $query->where('payment_status', 'paid'));
    }

    public function scopeArchived($query)
    {
        return $query->has('violations')->doesntHave('unpaidViolations');
    }

    public function scopeActive($query)
    {
        return $query->has('unpaidViolations');
    }

    public function isArchived(): bool
    {
        return $this->violations()->exists() && ! $this->unpaidViolations()->exists();
    }

    public function totalUnpaidFines()
    {
        return $this->violations()
            ->where('status', '!=', 'dismissed')
            ->whereHas('citation', fn($q) => $q->where('payment_status', '!=', 'paid'))
            ->count();
    }

    public static function repeatOffenderThreshold(): int
    {
        return (int) config('portals.repeat_offender_threshold', 3);
    }

    public function isRepeatOffender(): bool
    {
        return $this->countedViolations()->count() >= self::repeatOffenderThreshold();
    }
}
