<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'appointments.create',
                'display_name' => 'Randevu Oluşturma',
                'module' => 'appointments',
                'description' => 'İşletme adına randevu oluşturma yetkisi.',
            ],
            [
                'name' => 'appointments.confirm',
                'display_name' => 'Randevu Onaylama',
                'module' => 'appointments',
                'description' => 'Bekleyen randevuları onaylama yetkisi.',
            ],
            [
                'name' => 'appointments.cancel',
                'display_name' => 'Randevu İptal Etme',
                'module' => 'appointments',
                'description' => 'Bekleyen veya onaylanmış randevuları iptal etme yetkisi.',
            ],
            [
                'name' => 'appointments.reschedule',
                'display_name' => 'Randevu Tarihini Değiştirme',
                'module' => 'appointments',
                'description' => 'Bekleyen veya onaylanmış randevuların tarih ve saatini değiştirme yetkisi.',
            ],
            [
                'name' => 'appointments.complete',
                'display_name' => 'Randevu Tamamlama',
                'module' => 'appointments',
                'description' => 'Onaylanmış randevuları tamamlandı olarak işaretleme yetkisi.',
            ],
            [
                'name' => 'appointments.mark_no_show',
                'display_name' => 'Randevuya Gelmedi İşaretleme',
                'module' => 'appointments',
                'description' => 'Onaylanmış randevuları hasta gelmedi olarak işaretleme yetkisi.',
            ],
            [
                'name' => 'branches.manage',
                'display_name' => 'Şube Yönetimi',
                'module' => 'branches',
                'description' => 'İşletmenin şubelerini oluşturma, güncelleme ve aktif/pasif yönetme yetkisi.',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'name' => $permission['name'],
                ],
                [
                    'display_name' => $permission['display_name'],
                    'module' => $permission['module'],
                    'description' => $permission['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}