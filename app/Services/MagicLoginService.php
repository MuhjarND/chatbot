<?php

namespace App\Services;

use App\Employee;
use App\Application;
use App\EmployeeAppAccount;
use App\LoginToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MagicLoginService
{
    /**
     * Create a magic login token for an employee and application.
     *
     * @param Employee $employee
     * @param string   $applicationCode
     * @return string|null  Raw token (to be sent to user) or null on failure
     */
    public function createToken(Employee $employee, string $applicationCode): ?string
    {
        // Generate cryptographically secure random token (32 bytes = 64 hex chars)
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $ttl       = config('chatbot.magic_link_ttl_minutes', 5);

        LoginToken::create([
            'employee_id'      => $employee->id,
            'application_code' => $applicationCode,
            'token_hash'       => $tokenHash,
            'expires_at'       => Carbon::now()->addMinutes($ttl),
        ]);

        // Never log the raw token
        Log::info('MagicLoginService: Token created', [
            'employee_id'      => $employee->id,
            'application_code' => $applicationCode,
        ]);

        return $rawToken;
    }

    /**
     * Validate a magic login token.
     *
     * @param string      $rawToken
     * @param string      $applicationCode
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array  ['valid' => bool, ...additional data]
     */
    public function validateToken(string $rawToken, string $applicationCode, ?string $ip = null, ?string $userAgent = null): array
    {
        $tokenHash = hash('sha256', $rawToken);

        return DB::transaction(function () use ($tokenHash, $applicationCode, $ip, $userAgent) {
            // Find valid token with pessimistic locking to prevent race conditions
            $loginToken = LoginToken::where('token_hash', $tokenHash)
                ->where('application_code', $applicationCode)
                ->whereNull('used_at')
                ->where('expires_at', '>', Carbon::now())
                ->lockForUpdate()
                ->first();

            if (!$loginToken) {
                Log::info('MagicLoginService: Token validation failed - invalid or expired', [
                    'application_code' => $applicationCode,
                ]);
                return [
                    'valid'   => false,
                    'message' => 'Link login tidak valid atau sudah kedaluwarsa.',
                ];
            }

            // Check employee is active
            $employee = Employee::find($loginToken->employee_id);
            if (!$employee || !$employee->is_active) {
                Log::info('MagicLoginService: Token validation failed - employee inactive', [
                    'employee_id' => $loginToken->employee_id,
                ]);
                return [
                    'valid'   => false,
                    'message' => 'Link login tidak valid atau sudah kedaluwarsa.',
                ];
            }

            // Check application is active
            $application = Application::where('code', $applicationCode)->active()->first();
            if (!$application) {
                Log::info('MagicLoginService: Token validation failed - application inactive', [
                    'application_code' => $applicationCode,
                ]);
                return [
                    'valid'   => false,
                    'message' => 'Link login tidak valid atau sudah kedaluwarsa.',
                ];
            }

            // Check employee_app_account is active
            $appAccount = EmployeeAppAccount::where('employee_id', $employee->id)
                ->where('application_code', $applicationCode)
                ->active()
                ->first();

            if (!$appAccount) {
                Log::info('MagicLoginService: Token validation failed - app account inactive', [
                    'employee_id'      => $employee->id,
                    'application_code' => $applicationCode,
                ]);
                return [
                    'valid'   => false,
                    'message' => 'Link login tidak valid atau sudah kedaluwarsa.',
                ];
            }

            // Mark token as used (single-use)
            $loginToken->update([
                'used_at'    => Carbon::now(),
                'ip_used'    => $ip,
                'user_agent' => $userAgent,
            ]);

            Log::info('MagicLoginService: Token validated successfully', [
                'employee_id'      => $employee->id,
                'application_code' => $applicationCode,
            ]);

            return [
                'valid'            => true,
                'employee_id'      => $employee->id,
                'app_user_id'      => $appAccount->app_user_id,
                'name'             => $employee->name,
                'role'             => $employee->role,
                'application_code' => $applicationCode,
            ];
        });
    }
}
