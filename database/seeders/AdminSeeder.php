<?php

namespace Database\Seeders;

use App\Models\Administrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Administrator::create([
            'name' => 'Admin',
            'email' => 'prueba@gmail.com',
            'password' => Hash::make('prueba'),
        ]);
    }
}
