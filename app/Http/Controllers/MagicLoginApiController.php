<?php

namespace App\Http\Controllers;

use App\Services\MagicLoginService;
use App\Services\AccessLogService;
use Illuminate\Http\Request;

class MagicLoginApiController extends Controller
{
    protected $magicLoginService;
    protected $accessLogService;

    public function __construct(
        MagicLoginService $magicLoginService,
        AccessLogService $accessLogService
    ) {
        $this->magicLoginService = $magicLoginService;
        $this->accessLogService  = $accessLogService;
    }

    /**
     * Validate a magic login token.
     *
     * POST /api/magic-login/validate
     * Header: X-INTERNAL-API-KEY
     * Body: token, application_code
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'token'            => 'required|string',
            'application_code' => 'required|string',
        ]);

        $rawToken        = $request->input('token');
        $applicationCode = $request->input('application_code');
        $ip              = $request->ip();
        $userAgent       = $request->userAgent();

        $result = $this->magicLoginService->validateToken(
            $rawToken,
            $applicationCode,
            $ip,
            $userAgent
        );

        // Log the validation attempt
        $this->accessLogService->log([
            'employee_id'      => $result['employee_id'] ?? null,
            'application_code' => $applicationCode,
            'action'           => 'magic_login_validate',
            'status'           => $result['valid'] ? 'success' : 'failed',
            'message'          => $result['valid'] ? 'Token validated' : ($result['message'] ?? 'Invalid token'),
            'ip_address'       => $ip,
            'user_agent'       => $userAgent,
        ]);

        if ($result['valid']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 401);
    }
}
