<?php

use Illuminate\Database\Seeder;
use App\Application;

class ApplicationsTableSeeder extends Seeder
{
    /**
     * Seed the applications table.
     * Safe to run multiple times (uses updateOrCreate).
     */
    public function run()
    {
        $applications = [
            [
                'code'     => 'absensi',
                'name'     => 'Absensi',
                'base_url' => config('chatbot.app_base_urls.absensi', 'https://absensi.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'aplikasicuti',
                'name'     => 'Aplikasi Cuti',
                'base_url' => config('chatbot.app_base_urls.aplikasicuti', 'https://aplikasicuti.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'bakusapa',
                'name'     => 'Bakusapa',
                'base_url' => config('chatbot.app_base_urls.bakusapa', 'https://bakusapa.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'bukutamu',
                'name'     => 'Buku Tamu',
                'base_url' => config('chatbot.app_base_urls.bukutamu', 'https://bukutamu.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'koperasi',
                'name'     => 'Koperasi',
                'base_url' => config('chatbot.app_base_urls.koperasi', 'https://koperasi.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'sikasuar',
                'name'     => 'Sikasuar',
                'base_url' => config('chatbot.app_base_urls.sikasuar', 'https://sikasuar.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'simisol',
                'name'     => 'Simisol',
                'base_url' => config('chatbot.app_base_urls.simisol', 'https://simisol.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'siperlatin',
                'name'     => 'Siperlatin',
                'base_url' => config('chatbot.app_base_urls.siperlatin', 'https://siperlatin.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'smart',
                'name'     => 'Smart',
                'base_url' => config('chatbot.app_base_urls.smart', 'https://smart.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'survey',
                'name'     => 'Survey',
                'base_url' => config('chatbot.app_base_urls.survey', 'https://survey.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'tes',
                'name'     => 'Tes',
                'base_url' => config('chatbot.app_base_urls.tes', 'https://tes.pta-papuabarat.go.id'),
            ],
            [
                'code'     => 'wfh',
                'name'     => 'WFH',
                'base_url' => config('chatbot.app_base_urls.wfh', 'https://wfh.pta-papuabarat.go.id'),
            ],
        ];

        foreach ($applications as $app) {
            Application::updateOrCreate(
                ['code' => $app['code']],
                [
                    'name'      => $app['name'],
                    'base_url'  => $app['base_url'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Applications seeded: ' . count($applications) . ' entries.');
    }
}
