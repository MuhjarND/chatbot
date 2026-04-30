<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'nip',
        'email',
        'whatsapp_number',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the app accounts for this employee.
     */
    public function appAccounts()
    {
        return $this->hasMany(EmployeeAppAccount::class);
    }

    /**
     * Get active app accounts with their application info.
     */
    public function activeAppAccounts()
    {
        return $this->hasMany(EmployeeAppAccount::class)
                    ->where('employee_app_accounts.is_active', true);
    }

    /**
     * Get login tokens for this employee.
     */
    public function loginTokens()
    {
        return $this->hasMany(LoginToken::class);
    }

    /**
     * Get access logs for this employee.
     */
    public function accessLogs()
    {
        return $this->hasMany(AccessLog::class);
    }

    /**
     * Scope: active employees only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
