<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Field;
use App\Models\Booking;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BookingSeeder extends Seeder
{
    public function run()
    {
        // Obtener todos los usuarios y campos
        $users = User::all();
        $fields = Field::all();

        // Crear 20 reservaciones aleatorias
        foreach (range(1, 5) as $i) {
            $startTime = now()->addDays(rand(1, 30))->setHour(rand(8, 20));

            Booking::create([
                'user_id' => $users->random()->id, // Usuario aleatorio
                'field_id' => $fields->random()->id, // Campo aleatorio
                'start_time' => $startTime, // Inicio aleatorio
                'end_time' => $startTime->copy()->addHours(1), // Una hora después
                'total_price' => rand(100, 300), // Precio aleatorio
                'status' => 'pending', // Cambiar a un valor válido
                'payment_status' => 'pending', // Por defecto "pendiente"
                'allow_joining' => (bool)rand(0, 1), // Permitir unirse (aleatorio)
                'players_needed' => rand(1, 5), // Número aleatorio de jugadores necesarios
                'player_list' => [] // Lista de jugadores vacía por defecto
            ]);
        }
    }
}
