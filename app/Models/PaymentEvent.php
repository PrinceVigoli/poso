<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $guarded = [];
    protected $casts = ['before_state' => 'array', 'after_state' => 'array'];
    public function user() { return $this->belongsTo(User::class); }
}
