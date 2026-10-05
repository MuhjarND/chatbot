<?php

namespace App\Http\Controllers;

use App\Employee;
use App\Application;
use App\EmployeeAppAccount;
use App\LoginToken;
use App\Services\FonnteService;
use App\Services\WhatsappNumberService;
use App\Services\MagicLoginService;
use App\Services\AccessLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FonnteWebhookController extends Controller
{
    protected $fonnteService;
    protected $whatsappService;
    protected $magicLoginService;
    protected $accessLogService;

    public function __construct(
        FonnteService $fonnteService,
        WhatsappNumberService $whatsappService,
        MagicLoginService $magicLoginService,
        AccessLogService $accessLogService
    ) {
        $this->fonnteService    = $fonnteService;
        $this->whatsappService  = $whatsappService;
        $this->magicLoginService = $magicLoginService;
        $this->accessLogService = $accessLogService;
    }

    /**
     * Handle incoming Fonnte webhook.
     *
     * POST /api/webhook/fonnte
     */
    public function handle(Request $request)
    {
        $sender  = $request->input('sender', '');
        $message = trim($request->input('message', ''));

        // Normalize sender number
        $normalizedSender = $this->whatsappService->normalize($sender);

        Log::info('Webhook received', [
            'sender'  => $normalizedSender,
            'message' => $message,
        ]);

        // Find employee by whatsapp_number
        $employee = Employee::where('whatsapp_number', $normalizedSender)->first();

        if (!$employee) {
            $this->fonnteService->sendMessage(
                $normalizedSender,
                'Maaf, nomor WhatsApp Anda belum terdaftar sebagai pegawai. Silakan hubungi admin.'
            );

            $this->accessLogService->log([
                'action'  => 'webhook_unregistered',
                'status'  => 'failed',
                'message' => 'Nomor tidak terdaftar: ' . $normalizedSender,
            ]);

            return response()->json(['status' => true, 'message' => 'Webhook processed']);
        }

        if (!$employee->is_active) {
            $this->fonnteService->sendMessage(
                $normalizedSender,
                'Akun Anda tidak aktif. Silakan hubungi admin.'
            );

            $this->accessLogService->log([
                'employee_id' => $employee->id,
                'action'      => 'webhook_inactive',
                'status'      => 'failed',
                'message'     => 'Akun tidak aktif',
            ]);

            return response()->json(['status' => true, 'message' => 'Webhook processed']);
        }

        $messageLower = strtolower($message);

        // Handle menu/greetings
        if (in_array($messageLower, ['menu', 'halo', 'hi', 'start'])) {
            $this->handleMenu($employee, $normalizedSender);
            return response()->json(['status' => true, 'message' => 'Webhook processed']);
        }

        // Handle direct commands that open a specific module in an application.
        if ($this->handleDirectCommand($employee, $normalizedSender, $messageLower)) {
            return response()->json(['status' => true, 'message' => 'Webhook processed']);
        }

        // Handle numeric input (app selection)
        if (ctype_digit($message)) {
            $this->handleAppSelection($employee, $normalizedSender, (int) $message);
            return response()->json(['status' => true, 'message' => 'Webhook processed']);
        }

        // Unrecognized message
        $this->fonnteService->sendMessage(
            $normalizedSender,
            'Ketik *menu* untuk melihat daftar aplikasi.'
        );

        return response()->json(['status' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Handle a direct command that opens a module inside a specific application.
     */
    protected function handleDirectCommand(Employee $employee, string $sender, string $messageLower): bool
    {
        $commands = [
            'cuti' => [
                'application_code' => 'papeda',
                'redirect' => '/cuti/create',
                'label' => 'Pengajuan Cuti',
            ],
        ];

        if (!isset($commands[$messageLower])) {
            return false;
        }

        $command = $commands[$messageLower];
        $selectedApp = $this->getAvailableApplications($employee)
            ->first(function ($app) use ($command) {
                return strtolower(trim($app->code)) === $command['application_code'];
            });

        if (!$selectedApp) {
            $this->fonnteService->sendMessage(
                $sender,
                'Anda belum memiliki akses ke aplikasi Papeda. Silakan hubungi admin.'
            );

            $this->accessLogService->log([
                'employee_id' => $employee->id,
                'application_code' => $command['application_code'],
                'action' => 'webhook_direct_command_denied',
                'status' => 'failed',
                'message' => 'Direct command access denied: ' . $messageLower,
            ]);

            return true;
        }

        $this->sendMagicLoginLink(
            $employee,
            $sender,
            $selectedApp,
            $command['redirect'],
            $command['label']
        );

        return true;
    }

    /**
     * Show menu of active applications for the employee.
     */
    protected function handleMenu(Employee $employee, string $sender)
    {
        $accounts = $this->getActiveAccounts($employee);

        if ($accounts->isEmpty()) {
            Log::info('Webhook menu has no active app accounts', [
                'employee_id' => $employee->id,
                'sender' => $sender,
            ]);

            $this->fonnteService->sendMessage(
                $sender,
                'Anda belum memiliki akses ke aplikasi manapun. Silakan hubungi admin.'
            );
            return;
        }

        $apps = $this->getAvailableApplications($employee);

        if ($apps->isEmpty()) {
            Log::warning('Webhook menu accounts exist but no active applications resolved', [
                'employee_id' => $employee->id,
                'sender' => $sender,
                'account_application_codes' => $accounts->pluck('application_code')->values()->all(),
            ]);

            $this->fonnteService->sendMessage(
                $sender,
                'Tidak ada aplikasi aktif yang tersedia saat ini.'
            );
            return;
        }

        $menuText = "Halo *{$employee->name}* 👋\n\n";
        $menuText .= "Berikut daftar aplikasi yang dapat Anda akses:\n\n";

        $index = 1;
        foreach ($apps as $app) {
            $menuText .= "*{$index}.* {$app->name}\n";
            $index++;
        }

        $menuText .= "\nKetik *angka* untuk mendapatkan link login.\n";
        $menuText .= "Contoh: ketik *1* untuk login ke {$apps->first()->name}.";

        $this->fonnteService->sendMessage($sender, $menuText);

        $this->accessLogService->log([
            'employee_id' => $employee->id,
            'action'      => 'webhook_menu',
            'status'      => 'success',
            'message'     => 'Menu ditampilkan',
        ]);
    }

    /**
     * Handle application selection by number.
     */
    protected function handleAppSelection(Employee $employee, string $sender, int $selection)
    {
        $apps = $this->getAvailableApplications($employee);

        if ($selection < 1 || $selection > $apps->count()) {
            $this->fonnteService->sendMessage(
                $sender,
                'Pilihan tidak valid. Ketik *menu* untuk melihat daftar aplikasi.'
            );
            return;
        }

        $selectedApp = $apps[$selection - 1];

        $this->sendMagicLoginLink($employee, $sender, $selectedApp);
    }

    /**
     * Create and send a magic login link, optionally opening a module directly.
     */
    protected function sendMagicLoginLink(
        Employee $employee,
        string $sender,
        $selectedApp,
        string $redirect = '',
        string $destinationLabel = ''
    ): void {
        // Rate limit check: max tokens per employee in window
        $maxTokens = config('chatbot.rate_limit_max_tokens', 5);
        $windowMinutes = config('chatbot.rate_limit_window_minutes', 10);

        $recentTokenCount = LoginToken::where('employee_id', $employee->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes($windowMinutes))
            ->count();

        if ($recentTokenCount >= $maxTokens) {
            $this->fonnteService->sendMessage(
                $sender,
                'Anda terlalu sering meminta link. Silakan coba beberapa menit lagi.'
            );

            $this->accessLogService->log([
                'employee_id' => $employee->id,
                'application_code' => $selectedApp->code,
                'action' => 'webhook_rate_limited',
                'status' => 'failed',
                'message' => 'Rate limit exceeded',
            ]);

            return;
        }

        $rawToken = $this->magicLoginService->createToken($employee, $selectedApp->code);

        if (!$rawToken) {
            $this->fonnteService->sendMessage(
                $sender,
                'Terjadi kesalahan saat membuat link login. Silakan coba lagi.'
            );
            return;
        }

        $ttl = config('chatbot.magic_link_ttl_minutes', 5);
        $query = ['token' => $rawToken];

        if ($redirect !== '') {
            $query['redirect'] = $redirect;
        }

        $link = rtrim($selectedApp->base_url, '/') . '/autologin?' . http_build_query($query);
        $destinationLabel = $destinationLabel ?: $selectedApp->name;
        $replyMessage = "Silakan buka *{$destinationLabel}* melalui link berikut:\n\n";
        $replyMessage .= "{$link}\n\n";
        $replyMessage .= "Link berlaku selama {$ttl} menit dan hanya bisa digunakan satu kali.";

        $this->fonnteService->sendMessage($sender, $replyMessage);

        $this->accessLogService->log([
            'employee_id' => $employee->id,
            'application_code' => $selectedApp->code,
            'action' => $redirect !== '' ? 'webhook_direct_magic_link_sent' : 'webhook_magic_link_sent',
            'status' => 'success',
            'message' => $redirect !== ''
                ? 'Magic link sent for ' . $destinationLabel
                : 'Magic link sent for ' . $selectedApp->name,
        ]);
    }

    /**
     * Get active application account rows for the employee.
     */
    protected function getActiveAccounts(Employee $employee)
    {
        return EmployeeAppAccount::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Resolve active applications available to the employee.
     *
     * The join normalizes application codes to avoid hidden data issues such as
     * accidental spaces or uppercase codes in employee_app_accounts.
     */
    protected function getAvailableApplications(Employee $employee)
    {
        $apps = Application::query()
            ->select('applications.*')
            ->join('employee_app_accounts', function ($join) {
                $join->on(
                    DB::raw('LOWER(TRIM(employee_app_accounts.application_code))'),
                    '=',
                    DB::raw('LOWER(TRIM(applications.code))')
                );
            })
            ->where('employee_app_accounts.employee_id', $employee->id)
            ->where('employee_app_accounts.is_active', true)
            ->where('applications.is_active', true)
            ->distinct()
            ->orderBy('applications.name')
            ->get();

        Log::info('Webhook available applications resolved', [
            'employee_id' => $employee->id,
            'account_application_codes' => $this->getActiveAccounts($employee)
                ->pluck('application_code')
                ->values()
                ->all(),
            'resolved_application_codes' => $apps->pluck('code')->values()->all(),
        ]);

        return $apps;
    }
}
