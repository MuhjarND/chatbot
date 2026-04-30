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
     * Show menu of active applications for the employee.
     */
    protected function handleMenu(Employee $employee, string $sender)
    {
        $accounts = EmployeeAppAccount::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->get();

        if ($accounts->isEmpty()) {
            $this->fonnteService->sendMessage(
                $sender,
                'Anda belum memiliki akses ke aplikasi manapun. Silakan hubungi admin.'
            );
            return;
        }

        // Load active applications matching the employee's accounts
        $appCodes = $accounts->pluck('application_code')->toArray();
        $apps = Application::whereIn('code', $appCodes)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($apps->isEmpty()) {
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
        // Get employee's active app accounts
        $accounts = EmployeeAppAccount::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->get();

        $appCodes = $accounts->pluck('application_code')->toArray();
        $apps = Application::whereIn('code', $appCodes)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($selection < 1 || $selection > $apps->count()) {
            $this->fonnteService->sendMessage(
                $sender,
                'Pilihan tidak valid. Ketik *menu* untuk melihat daftar aplikasi.'
            );
            return;
        }

        $selectedApp = $apps[$selection - 1];

        // Rate limit check: max tokens per employee in window
        $maxTokens    = config('chatbot.rate_limit_max_tokens', 5);
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
                'employee_id'      => $employee->id,
                'application_code' => $selectedApp->code,
                'action'           => 'webhook_rate_limited',
                'status'           => 'failed',
                'message'          => 'Rate limit exceeded',
            ]);

            return;
        }

        // Create magic login token
        $rawToken = $this->magicLoginService->createToken($employee, $selectedApp->code);

        if (!$rawToken) {
            $this->fonnteService->sendMessage(
                $sender,
                'Terjadi kesalahan saat membuat link login. Silakan coba lagi.'
            );
            return;
        }

        $ttl  = config('chatbot.magic_link_ttl_minutes', 5);
        $link = $selectedApp->base_url . '/autologin?token=' . $rawToken;

        $replyMessage = "Silakan buka aplikasi *{$selectedApp->name}* melalui link berikut:\n\n";
        $replyMessage .= "{$link}\n\n";
        $replyMessage .= "Link berlaku selama {$ttl} menit dan hanya bisa digunakan satu kali.";

        $this->fonnteService->sendMessage($sender, $replyMessage);

        $this->accessLogService->log([
            'employee_id'      => $employee->id,
            'application_code' => $selectedApp->code,
            'action'           => 'webhook_magic_link_sent',
            'status'           => 'success',
            'message'          => 'Magic link sent for ' . $selectedApp->name,
        ]);
    }
}
