<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoginToken extends Model
{
    protected $fillable = [
        'employee_id',
        'application_code',
        'token_hash',
        'expires_at',
        'used_at',
        'ip_used',
        'user_agent',
    ];

    protected $dates = [
        'expires_at',
        'used_at',
    ];

    /**
     * Get the employee that owns this token.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the application for this token.
     */
    public function application()
    {
        return $this->belongsTo(Application::class, 'application_code', 'code');
    }

    /**
     * Check if this token has expired.
     */
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if this token has been used.
     */
    public function isUsed()
    {
        return $this->used_at !== null;
    }

    /**
     * Scope: unused and not expired tokens.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                     ->where('expires_at', '>', now());
    }
}
