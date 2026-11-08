<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@tukir.id',
            'password' => Hash::make('admin123'),
            'role' => 'SUPER_ADMIN',
        ]);

        User::create([
            'name' => 'Budi Teknisi',
            'email' => 'technician@tukir.id',
            'password' => Hash::make('admin123'),
            'role' => 'TECHNICIAN',
        ]);

        $user = User::create([
            'name' => 'Andi Pemilik',
            'email' => 'user@tukir.id',
            'password' => Hash::make('admin123'),
            'role' => 'END_USER',
        ]);

        Device::create([
            'device_code' => 'TUKIR-GARAGE-01',
            'device_name' => 'Garasi Utama Andi',
            'owner_id' => $user->id,
            'is_online' => true,
        ]);
    }
}
