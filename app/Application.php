<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee app accounts for this application.
     */
    public function employeeAccounts()
    {
        return $this->hasMany(EmployeeAppAccount::class, 'application_code', 'code');
    }

    /**
     * Scope: active applications only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
