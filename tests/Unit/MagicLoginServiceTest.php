<?php

namespace Tests\Unit;

use App\Employee;
use App\Application;
use App\EmployeeAppAccount;
use App\LoginToken;
use App\Services\MagicLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagicLoginServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MagicLoginService();
    }

    protected function createTestData(): array
    {
        $employee = Employee::create([
            'name'             => 'Test User',
            'nip'              => '1234567890',
            'email'            => 'test@example.com',
            'whatsapp_number'  => '6281200000001',
            'role'             => 'pegawai',
            'is_active'        => true,
        ]);

        $application = Application::create([
            'code'      => 'wfh',
            'name'      => 'WFH',
            'base_url'  => 'https://wfh.pta-papuabarat.go.id',
            'is_active' => true,
        ]);

        $appAccount = EmployeeAppAccount::create([
            'employee_id'      => $employee->id,
            'application_code' => 'wfh',
            'app_user_id'      => '15',
            'is_active'        => true,
        ]);

        return compact('employee', 'application', 'appAccount');
    }

    /** @test */
    public function it_creates_a_token()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');

        $this->assertNotNull($rawToken);
        $this->assertEquals(64, strlen($rawToken)); // 32 bytes = 64 hex chars

        // Verify token is stored as hash
        $tokenHash = hash('sha256', $rawToken);
        $this->assertDatabaseHas('login_tokens', [
            'employee_id'      => $data['employee']->id,
            'application_code' => 'wfh',
            'token_hash'       => $tokenHash,
        ]);
    }

    /** @test */
    public function it_validates_a_valid_token()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');
        $result   = $this->service->validateToken($rawToken, 'wfh', '127.0.0.1', 'TestAgent');

        $this->assertTrue($result['valid']);
        $this->assertEquals($data['employee']->id, $result['employee_id']);
        $this->assertEquals('15', $result['app_user_id']);
        $this->assertEquals('Test User', $result['name']);
        $this->assertEquals('pegawai', $result['role']);
        $this->assertEquals('wfh', $result['application_code']);
    }

    /** @test */
    public function it_rejects_used_token()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');

        // First use should succeed
        $result1 = $this->service->validateToken($rawToken, 'wfh');
        $this->assertTrue($result1['valid']);

        // Second use should fail (single-use)
        $result2 = $this->service->validateToken($rawToken, 'wfh');
        $this->assertFalse($result2['valid']);
    }

    /** @test */
    public function it_rejects_expired_token()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');

        // Manually expire the token
        $tokenHash = hash('sha256', $rawToken);
        LoginToken::where('token_hash', $tokenHash)->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->validateToken($rawToken, 'wfh');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_rejects_token_for_wrong_application()
    {
        $data = $this->createTestData();

        // Create another application
        Application::create([
            'code'      => 'absensi',
            'name'      => 'Absensi',
            'base_url'  => 'https://absensi.pta-papuabarat.go.id',
            'is_active' => true,
        ]);

        $rawToken = $this->service->createToken($data['employee'], 'wfh');

        // Validate with wrong application code
        $result = $this->service->validateToken($rawToken, 'absensi');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_rejects_token_for_inactive_employee()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');

        // Deactivate employee
        $data['employee']->update(['is_active' => false]);

        $result = $this->service->validateToken($rawToken, 'wfh');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_rejects_invalid_token()
    {
        $this->createTestData();

        $result = $this->service->validateToken('invalid_token_string', 'wfh');
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_records_ip_and_user_agent_on_validation()
    {
        $data = $this->createTestData();

        $rawToken = $this->service->createToken($data['employee'], 'wfh');
        $this->service->validateToken($rawToken, 'wfh', '192.168.1.1', 'Mozilla/5.0');

        $tokenHash = hash('sha256', $rawToken);
        $this->assertDatabaseHas('login_tokens', [
            'token_hash' => $tokenHash,
            'ip_used'    => '192.168.1.1',
        ]);
    }
}
