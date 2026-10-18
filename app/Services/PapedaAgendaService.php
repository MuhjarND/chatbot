<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class PapedaAgendaService
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetch(string $baseUrl, string $appUserId): array
    {
        $key = (string) config('chatbot.internal_api_key', '');
        if ($key === '') {
            throw new \RuntimeException('Papeda agenda API key is not configured.');
        }

        $response = $this->client->post(rtrim($baseUrl, '/') . '/api/chatbot/agenda', [
            'headers' => [
                'X-INTERNAL-API-KEY' => $key,
                'Accept' => 'application/json',
            ],
            'form_params' => ['app_user_id' => $appUserId],
            'connect_timeout' => 3,
            'timeout' => 15,
            'allow_redirects' => false,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($data)
            || !isset($data['start_date'], $data['end_date'], $data['events'])
            || !is_array($data['events'])) {
            throw new \RuntimeException('Invalid Papeda agenda response.');
        }

        return $data;
    }

    public function messages(string $name, array $data): array
    {
        $start = Carbon::parse($data['start_date'], 'Asia/Jayapura')->locale('id')->translatedFormat('d M Y');
        $end = Carbon::parse($data['end_date'], 'Asia/Jayapura')->locale('id')->translatedFormat('d M Y');
        $header = '*Agenda Pribadi Papeda*' . "\n" . $this->text($name, 150) . "\n{$start} - {$end} (WIT)\n\n";

        if (empty($data['events'])) {
            return [$header . 'Tidak ada agenda pribadi pada periode ini.'];
        }

        $messages = [];
        $message = $header;
        foreach ($data['events'] as $index => $event) {
            $date = Carbon::parse($event['start'])->timezone('Asia/Jayapura')->locale('id')->translatedFormat('d M Y');
            if (!empty($event['all_day'])) {
                $lastDay = Carbon::parse($event['end'])->timezone('Asia/Jayapura')->subDay()->locale('id');
                if ($lastDay->toDateString() !== Carbon::parse($event['start'])->timezone('Asia/Jayapura')->toDateString()) {
                    $date .= ' - ' . $lastDay->translatedFormat('d M Y');
                }
            }
            $time = !empty($event['all_day']) ? 'Sepanjang hari' : $this->text($event['time_label'], 180);
            $entry = '*' . ($index + 1) . '. ' . $this->text($event['title'], 255) . "*\n"
                . $date . ' | ' . $time . "\n"
                . 'Tempat: ' . $this->text($event['location'], 255) . "\n"
                . $this->text($event['module'], 80) . ' | ' . $this->text($event['status'], 80) . "\n\n";

            if (mb_strlen($message . $entry) > 3500) {
                $messages[] = trim($message);
                $message = $header;
            }
            $message .= $entry;
        }
        $messages[] = trim($message);

        return $messages;
    }

    protected function text($value, int $limit): string
    {
        $value = preg_replace('/\s+/u', ' ', strip_tags((string) $value));
        return Str::limit(str_replace(['*', '_', '~', '`'], '', $value), $limit, '...') ?: '-';
    }
}
