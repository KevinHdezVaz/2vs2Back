<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Field;


class UpdateImagesSeeder extends Seeder
{
    

    public function run()
    {
        // Obtener todos los registros y mover los datos de image_url a images
        $fields = Field::all();
    
        foreach ($fields as $field) {
            if ($field->image_url) {
                // Convertir la URL de image_url a un array y asignarlo a images
                $field->images = json_encode([$field->image_url]);
                $field->save();
            }
        }
    }
    
}
