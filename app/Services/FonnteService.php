<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Send a WhatsApp message via Fonnte API.
     *
     * @param string $target  Recipient phone number (format: 628xxx)
     * @param string $message Message text to send
     * @return array|null
     */
    public function sendMessage(string $target, string $message): ?array
    {
        $token   = config('chatbot.fonnte_token');
        $sendUrl = config('chatbot.fonnte_send_url');

        if (empty($token)) {
            Log::error('FonnteService: FONNTE_TOKEN is not configured.');
            return null;
        }

        try {
            $response = $this->postToFonnte($sendUrl, $token, [
                'target'      => $target,
                'message'     => $message,
                'countryCode' => '62',
            ]);

            $result = $response;

            // Log success/failure without exposing the token
            Log::info('FonnteService: Message sent', [
                'target' => $target,
                'status' => $result['status'] ?? 'unknown',
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('FonnteService: Failed to send message', [
                'target' => $target,
                'error'  => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Perform the actual HTTP POST to Fonnte.
     * Extracted for testability.
     *
     * @param string $url
     * @param string $token
     * @param array  $data
     * @return array
     */
    protected function postToFonnte(string $url, string $token, array $data): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $token,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Fonnte cURL error: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        return $decoded ?: ['status' => false, 'raw' => $response, 'http_code' => $httpCode];
    }
}
