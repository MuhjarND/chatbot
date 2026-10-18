<?php

namespace Tests\Feature;

use App\Application;
use App\Employee;
use App\EmployeeAppAccount;
use App\Services\FonnteService;
use App\Services\PapedaAgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $employee;
    protected $agenda;
    protected $fonnte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = Employee::create([
            'name' => 'Pegawai Pertama', 'whatsapp_number' => '6281200000001', 'is_active' => true,
        ]);
        $other = Employee::create([
            'name' => 'Pegawai Lain', 'whatsapp_number' => '6281200000002', 'is_active' => true,
        ]);
        Application::create([
            'code' => 'papeda', 'name' => 'Papeda', 'base_url' => 'https://papeda.example.test', 'is_active' => true,
        ]);
        foreach ([[$this->employee->id, '76'], [$other->id, '77']] as $mapping) {
            EmployeeAppAccount::create([
                'employee_id' => $mapping[0], 'application_code' => 'papeda',
                'app_user_id' => $mapping[1], 'is_active' => true,
            ]);
        }
        $this->agenda = \Mockery::mock(PapedaAgendaService::class);
        $this->fonnte = \Mockery::mock(FonnteService::class);
        $this->app->instance(PapedaAgendaService::class, $this->agenda);
        $this->app->instance(FonnteService::class, $this->fonnte);
    }

    public function test_agenda_uses_only_senders_mapping_and_sends_all_message_parts()
    {
        $data = ['events' => []];
        $this->agenda->shouldReceive('fetch')->once()->with('https://papeda.example.test', '76')->andReturn($data);
        $this->agenda->shouldReceive('messages')->once()->with('Pegawai Pertama', $data)
            ->andReturn(['Bagian pertama', 'Bagian kedua']);
        $this->fonnte->shouldReceive('sendMessage')->once()->with('6281200000001', 'Bagian pertama')
            ->andReturn(['status' => true]);
        $this->fonnte->shouldReceive('sendMessage')->once()->with('6281200000001', 'Bagian kedua')
            ->andReturn(['status' => true]);

        $this->webhook('  AGENDA  ')->assertOk();

        $this->assertDatabaseHas('access_logs', [
            'employee_id' => $this->employee->id, 'action' => 'webhook_agenda_sent', 'status' => 'success',
        ]);
        $this->assertDatabaseCount('login_tokens', 0);
    }

    public function test_inactive_mapping_prevents_agenda_request()
    {
        EmployeeAppAccount::where('employee_id', $this->employee->id)->update(['is_active' => false]);
        $this->agenda->shouldNotReceive('fetch');
        $this->fonnte->shouldReceive('sendMessage')->once()->with('6281200000001', \Mockery::on(function ($text) {
            return strpos($text, 'mapping akun Papeda') !== false;
        }))->andReturn(['status' => true]);

        $this->webhook('agenda')->assertOk();
    }

    public function test_inactive_application_prevents_agenda_request()
    {
        Application::where('code', 'papeda')->update(['is_active' => false]);
        $this->agenda->shouldNotReceive('fetch');
        $this->fonnte->shouldReceive('sendMessage')->once()->andReturn(['status' => true]);
        $this->webhook('agenda')->assertOk();
    }

    public function test_inactive_employee_prevents_agenda_request()
    {
        $this->employee->update(['is_active' => false]);
        $this->agenda->shouldNotReceive('fetch');
        $this->fonnte->shouldReceive('sendMessage')->once()->with('6281200000001', \Mockery::on(function ($text) {
            return strpos($text, 'Akun Anda tidak aktif') !== false;
        }))->andReturn(['status' => true]);
        $this->webhook('agenda')->assertOk();
    }

    public function test_user_cannot_supply_another_employees_id_in_command()
    {
        $this->agenda->shouldNotReceive('fetch');
        $this->fonnte->shouldReceive('sendMessage')->once()
            ->with('6281200000001', 'Ketik *menu* untuk melihat daftar aplikasi.')
            ->andReturn(['status' => true]);
        $this->webhook('agenda 77')->assertOk();
    }

    public function test_api_failure_returns_retry_message_without_leaking_error_details()
    {
        $this->agenda->shouldReceive('fetch')->once()->andThrow(new \RuntimeException('secret-key must not appear'));
        $this->agenda->shouldNotReceive('messages');
        $this->fonnte->shouldReceive('sendMessage')->once()->with('6281200000001', \Mockery::on(function ($text) {
            return strpos($text, 'belum dapat diambil') !== false && strpos($text, 'secret-key') === false;
        }))->andReturn(['status' => true]);
        $this->webhook('agenda')->assertOk();
    }

    protected function webhook(string $message)
    {
        return $this->postJson('/api/webhook/fonnte', ['sender' => '081200000001', 'message' => $message]);
    }
}
