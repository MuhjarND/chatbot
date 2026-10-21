<?php

namespace Tests\Feature;

use App\Application;
use App\Employee;
use App\EmployeeAppAccount;
use App\LoginToken;
use App\Services\FonnteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasuariWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $employee;
    protected $fonnte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = Employee::create([
            'name' => 'Pegawai Kasuari', 'whatsapp_number' => '6281200000001', 'is_active' => true,
        ]);
        Application::create([
            'code' => 'kasuari', 'name' => 'Kasuari',
            'base_url' => 'https://kasuari.pta-papuabarat.go.id', 'is_active' => true,
        ]);
        EmployeeAppAccount::create([
            'employee_id' => $this->employee->id, 'application_code' => 'kasuari',
            'app_user_id' => '12', 'is_active' => true,
        ]);
        $this->fonnte = \Mockery::mock(FonnteService::class);
        $this->app->instance(FonnteService::class, $this->fonnte);
    }

    public function test_kasuari_appears_in_menu_and_numeric_selection_generates_login_link()
    {
        $this->fonnte->shouldReceive('sendMessage')->once()
            ->with('6281200000001', \Mockery::on(function ($message) {
                return strpos($message, '*1.* Kasuari') !== false;
            }))->andReturn(['status' => true]);
        $this->expectLoginLink();

        $this->webhook('menu')->assertOk();
        $this->webhook('1')->assertOk();
        $this->assertDatabaseHas('login_tokens', [
            'employee_id' => $this->employee->id, 'application_code' => 'kasuari',
        ]);
    }

    public function test_direct_command_handles_case_and_whitespace()
    {
        $this->expectLoginLink();
        $this->webhook('  KASUARI  ')->assertOk();
        $this->assertDatabaseCount('login_tokens', 1);
    }

    public function test_inactive_mapping_does_not_generate_login_token()
    {
        EmployeeAppAccount::where('employee_id', $this->employee->id)->update(['is_active' => false]);
        $this->expectAccessDenied();
        $this->webhook('kasuari')->assertOk();
        $this->assertDatabaseCount('login_tokens', 0);
    }

    public function test_inactive_application_does_not_generate_login_token()
    {
        Application::where('code', 'kasuari')->update(['is_active' => false]);
        $this->expectAccessDenied();
        $this->webhook('kasuari')->assertOk();
        $this->assertDatabaseCount('login_tokens', 0);
    }

    protected function expectLoginLink(): void
    {
        $this->fonnte->shouldReceive('sendMessage')->once()
            ->with('6281200000001', \Mockery::on(function ($message) {
                if (!preg_match('~https://kasuari\.pta-papuabarat\.go\.id/autologin\?token=([a-f0-9]{64})~', $message, $matches)) {
                    return false;
                }
                return strpos($message, '*Kasuari*') !== false
                    && LoginToken::where('token_hash', hash('sha256', $matches[1]))->exists();
            }))->andReturn(['status' => true]);
    }

    protected function expectAccessDenied(): void
    {
        $this->fonnte->shouldReceive('sendMessage')->once()
            ->with('6281200000001', 'Anda belum memiliki akses ke aplikasi Kasuari. Silakan hubungi admin.')
            ->andReturn(['status' => true]);
    }

    protected function webhook(string $message)
    {
        return $this->postJson('/api/webhook/fonnte', ['sender' => '081200000001', 'message' => $message]);
    }
}
