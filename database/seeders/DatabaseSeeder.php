<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OltDevice;
use App\Models\Onu;
use App\Models\OnuConfiguration;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create OLT
        $olt = OltDevice::create([
            'name' => 'OLT-1',
            'ip_address' => '192.168.1.1',
            'username' => 'admin',
            'password' => 'admin123',
            'port' => 23,
            'is_active' => true,
        ]);

        $olt = OltDevice::create([
            'name' => 'DIAPA',
            'ip_address' => '161.248.152.2',
            'username' => 'zte',
            'password' => 'Penegak#28',
            'port' => 53726,
            'is_active' => true,
        ]);

        // Create ONUs
       
    
    }
}
