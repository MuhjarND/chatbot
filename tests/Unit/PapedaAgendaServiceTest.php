<?php

namespace Tests\Unit;

use App\Services\PapedaAgendaService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class PapedaAgendaServiceTest extends TestCase
{
    public function test_fetch_sends_mapped_identity_and_key_to_papeda()
    {
        config(['chatbot.internal_api_key' => 'test-key']);
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($this->data()))]));
        $handler->push(Middleware::history($history));
        $service = new PapedaAgendaService(new Client(['handler' => $handler]));

        $result = $service->fetch('https://papeda.example.test/', '76');

        $this->assertSame([], $result['events']);
        $request = $history[0]['request'];
        $this->assertSame('https://papeda.example.test/api/chatbot/agenda', (string) $request->getUri());
        $this->assertSame('test-key', $request->getHeaderLine('X-INTERNAL-API-KEY'));
        parse_str((string) $request->getBody(), $body);
        $this->assertSame(['app_user_id' => '76'], $body);
    }

    public function test_fetch_rejects_html_instead_of_treating_it_as_empty_agenda()
    {
        config(['chatbot.internal_api_key' => 'test-key']);
        $service = new PapedaAgendaService(new Client([
            'handler' => new MockHandler([new Response(200, [], '<html>Login</html>')]),
        ]));

        $this->expectException(\RuntimeException::class);
        $service->fetch('https://papeda.example.test', '76');
    }

    public function test_fetch_rejects_missing_key()
    {
        config(['chatbot.internal_api_key' => '']);
        $client = \Mockery::mock(Client::class);
        $client->shouldNotReceive('post');
        $service = new PapedaAgendaService($client);

        $this->expectException(\RuntimeException::class);
        $service->fetch('https://papeda.example.test', '76');
    }

    public function test_fetch_does_not_forward_key_through_redirects()
    {
        config(['chatbot.internal_api_key' => 'test-key']);
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(302, ['Location' => 'https://other.example.test/']),
        ]));
        $handler->push(Middleware::history($history));
        $service = new PapedaAgendaService(new Client(['handler' => $handler]));

        try {
            $service->fetch('https://papeda.example.test', '76');
            $this->fail('Redirect response must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertCount(1, $history);
            $this->assertSame(302, $history[0]['response']->getStatusCode());
        }
    }

    public function test_empty_agenda_shows_the_requested_period()
    {
        $messages = (new PapedaAgendaService(new Client()))->messages('Pegawai', $this->data());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('16 Okt 2026 - 22 Okt 2026', $messages[0]);
        $this->assertStringContainsString('Tidak ada agenda pribadi', $messages[0]);
    }

    public function test_long_agenda_is_split_without_omitting_events_and_uses_wit()
    {
        $data = $this->data();
        for ($i = 1; $i <= 30; $i++) {
            $data['events'][] = [
                'title' => 'Agenda ' . $i . ' ' . str_repeat('X', 200),
                'start' => '2026-10-16T23:30:00+00:00',
                'end' => '2026-10-17T00:30:00+00:00',
                'all_day' => false,
                'module' => 'Rapat',
                'status' => 'Dijadwalkan',
                'location' => 'Ruang rapat',
                'time_label' => '08:30 WIT',
            ];
        }
        $messages = (new PapedaAgendaService(new Client()))->messages('Pegawai', $data);
        $this->assertGreaterThan(1, count($messages));
        foreach ($messages as $message) {
            $this->assertLessThanOrEqual(3500, mb_strlen($message));
        }
        $joined = implode("\n", $messages);
        $this->assertStringContainsString('17 Okt 2026 | 08:30 WIT', $joined);
        for ($i = 1; $i <= 30; $i++) {
            $this->assertSame(1, substr_count($joined, '*' . $i . '. Agenda ' . $i . ' '));
        }
    }

    public function test_all_day_end_date_is_exclusive()
    {
        $data = $this->data();
        $data['events'][] = [
            'title' => 'Cuti',
            'start' => '2026-10-15T00:00:00+09:00',
            'end' => '2026-10-19T00:00:00+09:00',
            'all_day' => true,
            'module' => 'Cuti',
            'status' => 'Berjalan',
            'location' => '-',
        ];
        $messages = (new PapedaAgendaService(new Client()))->messages('Pegawai', $data);
        $this->assertStringContainsString('15 Okt 2026 - 18 Okt 2026 | Sepanjang hari', $messages[0]);
    }

    protected function data(): array
    {
        return ['start_date' => '2026-10-16', 'end_date' => '2026-10-22', 'events' => []];
    }
}
