<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $fillable = [
        'employee_id',
        'application_code',
        'action',
        'status',
        'message',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the employee for this log entry.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the application for this log entry.
     */
    public function application()
    {
        return $this->belongsTo(Application::class, 'application_code', 'code');
    }
}
