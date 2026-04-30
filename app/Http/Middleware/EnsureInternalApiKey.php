<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInternalApiKey
{
    /**
     * Handle an incoming request.
     * Validates X-INTERNAL-API-KEY header against the configured key.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredKey = config('chatbot.internal_api_key');

        // If no key is configured, deny all requests
        if (empty($configuredKey)) {
            return response()->json([
                'valid'   => false,
                'message' => 'Internal API key is not configured.',
            ], 401);
        }

        $providedKey = $request->header('X-INTERNAL-API-KEY', '');

        // Use hash_equals for timing-safe comparison
        if (!hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'valid'   => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
