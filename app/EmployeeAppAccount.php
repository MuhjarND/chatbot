<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmployeeAppAccount extends Model
{
    protected $fillable = [
        'employee_id',
        'application_code',
        'app_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee that owns this account.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the application for this account.
     */
    public function application()
    {
        return $this->belongsTo(Application::class, 'application_code', 'code');
    }

    /**
     * Scope: active accounts only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
