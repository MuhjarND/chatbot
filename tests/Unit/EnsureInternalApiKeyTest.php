<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureInternalApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class EnsureInternalApiKeyTest extends TestCase
{
    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureInternalApiKey();
    }

    /** @test */
    public function it_passes_with_valid_api_key()
    {
        $testKey = bin2hex(random_bytes(32));
        config(['chatbot.internal_api_key' => $testKey]);

        $request = Request::create('/test', 'POST');
        $request->headers->set('X-INTERNAL-API-KEY', $testKey);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['passed' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_with_invalid_api_key()
    {
        config(['chatbot.internal_api_key' => 'correct_key_here']);

        $request = Request::create('/test', 'POST');
        $request->headers->set('X-INTERNAL-API-KEY', 'wrong_key_here');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['passed' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_with_missing_api_key()
    {
        config(['chatbot.internal_api_key' => 'some_configured_key']);

        $request = Request::create('/test', 'POST');
        // No X-INTERNAL-API-KEY header

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['passed' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_when_no_key_configured()
    {
        config(['chatbot.internal_api_key' => null]);

        $request = Request::create('/test', 'POST');
        $request->headers->set('X-INTERNAL-API-KEY', 'any_key');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['passed' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_when_empty_key_configured()
    {
        config(['chatbot.internal_api_key' => '']);

        $request = Request::create('/test', 'POST');
        $request->headers->set('X-INTERNAL-API-KEY', '');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['passed' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }
}
