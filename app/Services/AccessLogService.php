<?php

namespace App\Services;

use App\AccessLog;

class AccessLogService
{
    /**
     * Log an access event.
     *
     * @param array $data
     * @return AccessLog
     */
    public function log(array $data): AccessLog
    {
        return AccessLog::create([
            'employee_id'      => $data['employee_id'] ?? null,
            'application_code' => $data['application_code'] ?? null,
            'action'           => $data['action'],
            'status'           => $data['status'],
            'message'          => $data['message'] ?? null,
            'ip_address'       => $data['ip_address'] ?? null,
            'user_agent'       => $data['user_agent'] ?? null,
        ]);
    }
}
