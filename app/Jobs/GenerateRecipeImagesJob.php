<?php

namespace App\Jobs;

use App\Models\MealPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateRecipeImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mealPlanId;
    public $timeout = 600; // Aumentamos el timeout por si son muchas imágenes
    public $tries = 2;

    public function __construct($mealPlanId)
    {
        $this->mealPlanId = $mealPlanId;
    }

    public function handle()
    {
        Log::info('Iniciando GenerateRecipeImagesJob', ['mealPlanId' => $this->mealPlanId]);
        $mealPlan = MealPlan::find($this->mealPlanId);

        if (!$mealPlan) {
            Log::error('MealPlan no encontrado en GenerateRecipeImagesJob', ['mealPlanId' => $this->mealPlanId]);
            return;
        }

        $planData = $mealPlan->plan_data;
        $planModified = false;

        // Usamos referencias (&) para poder modificar el array original directamente
        foreach ($planData['nutritionPlan']['meals'] as &$meal) {
            if (isset($meal['suggested_recipes']) && is_array($meal['suggested_recipes'])) {
                foreach ($meal['suggested_recipes'] as &$recipe) {
                    
                    // Solo generamos imagen si no existe o está en null
                    if (isset($recipe['name']) && empty($recipe['image'])) {
                        Log::info('Generando imagen para la receta.', ['recipe' => $recipe['name']]);
                        
                        $imagePath = $this->generateAndStoreImage($recipe['name']);
                        
                        if ($imagePath) {
                            $recipe['image'] = $imagePath; // Guardamos la ruta relativa
                            $planModified = true;
                            Log::info('Imagen generada y asignada.', ['path' => $imagePath]);
                        }
                    }
                }
            }
        }

        // Solo actualizamos la BD si se generó al menos una imagen
        if ($planModified) {
            $mealPlan->plan_data = $planData;
            $mealPlan->save();
            Log::info('Plan actualizado con nuevas imágenes.', ['mealPlanId' => $this->mealPlanId]);
        } else {
            Log::info('No se necesitaron generar nuevas imágenes para este plan.', ['mealPlanId' => $this->mealPlanId]);
        }
    }

    /**
     * Genera una imagen con DALL-E y la guarda en el storage.
     * Devuelve la ruta relativa de la imagen o null si falla.
     */
    private function generateAndStoreImage(string $recipeName): ?string
    {
        // Prompt optimizado para un buen resultado visual
        $prompt = "Fotografía de comida profesional, plato delicioso de '{$recipeName}', servido en un plato blanco, fondo limpio y brillante.";

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(120)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024', // Cambiar '512x512' por '1024x1024'
                    'quality' => 'standard',   // 'standard' es más barato que 'hd'
                    'response_format' => 'b64_json', // Para recibir la imagen directamente y no usar una URL temporal
                ]);

            if ($response->successful()) {
                $b64Json = $response->json('data.0.b64_json');
                $imageData = base64_decode($b64Json);
                
                // Genera un nombre de archivo único
                $filename = 'recipe_images/' . uniqid('recipe_', true) . '.png';
                
                // Guarda la imagen en el disco público
                Storage::disk('public')->put($filename, $imageData);
                
                return $filename; // Retorna la ruta relativa
            }

            Log::error('La llamada a DALL-E falló', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            Log::error('Excepción al generar imagen con DALL-E', [
                'recipeName' => $recipeName, 'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
}