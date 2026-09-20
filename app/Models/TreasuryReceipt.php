<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryReceipt extends Model
{
    protected $guarded = [];
    protected $casts = ['receipt_date' => 'date', 'total_amount' => 'decimal:2', 'needs_review' => 'boolean'];
    public static function key(string $number): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($number)));
    }
    public function allocations() { return $this->hasMany(Citation::class); }
}
