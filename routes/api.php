<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Chatbot Gateway Routes
|--------------------------------------------------------------------------
*/

// Fonnte Webhook — receives incoming WhatsApp messages
Route::post('/webhook/fonnte', 'FonnteWebhookController@handle')
    ->name('webhook.fonnte');

// Magic Login Token Validation — used by internal applications
Route::post('/magic-login/validate', 'MagicLoginApiController@validateToken')
    ->middleware('internal.api.key')
    ->name('magic-login.validate');
