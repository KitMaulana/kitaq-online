<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Super Admin bawaan: superadmin / KitaQ#2026 — WAJIB ganti saat login pertama. */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $user = User::query()->firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => null,
                'password' => 'KitaQ#2026',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
        $user->syncRoles(['super_admin']);
    }
}
