<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'module', 'details', 'ip_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper: log any action quickly
    public static function record(string $action, string $module, string $details): void
    {
        self::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'module'     => $module,
            'details'    => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
