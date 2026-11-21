<?php

namespace Database\Seeders;

use App\Models\PlatformContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ClinicMembershipContractDraftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PlatformContract::firstOrCreate([
            'contract_type' => 'clinic_membership',
            'version' => '0.1-draft',
        ], [
            'title' => 'Medloby Klinik Üyelik ve Platform Kullanım Sözleşmesi',
            'content' => File::get(resource_path('contracts/medloby-klinik-uyelik-taslak.txt')),
            'status' => 'draft',
            'is_required' => true,
        ]);
    }
}
