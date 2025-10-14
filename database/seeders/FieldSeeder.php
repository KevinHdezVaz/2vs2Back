<?php

namespace Database\Seeders;

use App\Models\Field;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;


class FieldSeeder extends Seeder
{
    public function run()
    {
        Field::create([
            'name' => 'Cancha 1',
            'description' => 'Cancha de fútbol 5',
            'location' => 'Zona Norte',
            'price_per_hour' => 100.00,
            'available_hours' => ['10:00', '11:00', '12:00'],
            'amenities' => ['Vestuarios', 'Estacionamiento'],
            'image_url' => 'https://example.com/image1.jpg',
        ]);

        Field::create([
            'name' => 'Cancha 2',
            'description' => 'Cancha de fútbol 7',
            'location' => 'Zona Sur',
            'price_per_hour' => 200.00,
            'available_hours' => ['13:00', '14:00', '15:00'],
            'amenities' => ['Iluminación nocturna', 'Estacionamiento'],
            'image_url' => 'https://example.com/image2.jpg',
        ]);
    }
}
