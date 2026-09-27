<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ViolationType extends Model
{
    use SoftDeletes;
    protected $fillable = ['offense_name', 'category', 'fine_amount', 'description', 'offense_key', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::creating(function (self $type) { $type->offense_key ??= (string) \Illuminate\Support\Str::uuid(); });
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }

    public function getFineLabelAttribute(): string
    {
        return $this->fine_amount === null ? 'Not configured' : "\u{20B1}".number_format($this->fine_amount, 2);
    }
}
