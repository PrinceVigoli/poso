<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'is_active'];
    protected $hidden   = ['password', 'remember_token'];
    protected $attributes = ['is_active' => true, 'role' => 'enforcer', 'credential_version' => 0];

    protected static function booted(): void
    {
        static::updating(function (self $user) {
            if ($user->isDirty(['password', 'role', 'is_active'])) {
                $user->credential_version = (int) $user->getOriginal('credential_version') + 1;
                $user->remember_token = null;
            }
        });
    }

    // Fix: added is_active and proper date casts
    protected $casts = [
        'is_active'         => 'boolean',
        'credential_version' => 'integer',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function violations()
    {
        return $this->hasMany(Violation::class, 'officer_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin():    bool { return $this->role === 'admin'; }
    public function isEnforcer(): bool { return $this->role === 'enforcer'; }
}
