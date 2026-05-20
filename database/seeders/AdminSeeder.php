<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'nomComplet' => 'Admin Cosmetique',
            'email' => 'admin@cosmetique.com',
            'password' => Hash::make('admin123'),
            'tel' => '781857313',
            'role' => 'ADMIN'
        ]);
    }
}
