<?php

namespace App\Http\Controllers\API;

use App\Models\Equipo;
use App\Models\Torneo;
use Illuminate\Http\Request;
use App\Models\EstadisticaTorneo;
use App\Http\Controllers\Controller;
use App\Http\Resources\TorneoResource;

class TorneoAPIController extends Controller
{
   
  // TorneoAPIController
public function index()
{
    try {
        $torneos = Torneo::all()->map(function($torneo) {
            if ($torneo->imagenesTorneo) {
                $imagenes = json_decode($torneo->imagenesTorneo);
                // Construir URL completa
                $imagenes = array_map(function($imagen) {
                    return asset('storage/' . $imagen);
                }, $imagenes);
                $torneo->imagenesTorneo = $imagenes;
            }
            return $torneo;
        });

        return response()->json([
            'success' => true,
            'data' => $torneos
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener los torneos',
            'error' => $e->getMessage()
        ], 500);
    }
}
   

 


// TorneoAPIController.php
public function show($id)
{
    try {
        $torneo = Torneo::findOrFail($id);
        
        // Procesar las imágenes para devolver URLs completas
        if ($torneo->imagenesTorneo) {
            // Verificar si imagenesTorneo es una cadena JSON o un array
            if (is_string($torneo->imagenesTorneo)) {
                $imagenes = json_decode($torneo->imagenesTorneo, true); // Decodificar si es una cadena JSON
            } else {
                $imagenes = $torneo->imagenesTorneo; // Ya es un array
            }

            // Convertir las rutas de las imágenes en URLs completas
            $imagenesConURL = array_map(function($imagen) {
                return url($imagen); // Esto convertirá /storage/... en https://tu-dominio.com/storage/...
            }, $imagenes);

            $torneo->imagenesTorneo = $imagenesConURL; // Asignar el array de URLs completas
        }

        // Manejar standings (necesitas crear este modelo)
        $standings = EstadisticaTorneo::where('torneo_id', $id)
            ->orderBy('posicion')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $torneo,
            'standings' => $standings,
            'rules' => is_string($torneo->reglas) ? json_decode($torneo->reglas, true) : $torneo->reglas,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener los detalles del torneo',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getTorneosByStatus($status)
    {
        try {
            $torneos = Torneo::where('estado', $status)
                            ->orderBy('created_at', 'desc')
                            ->get();

            $torneos->transform(function ($torneo) {
                if ($torneo->imagenesTorneo) {
                    $torneo->imagenesTorneo = json_decode($torneo->imagenesTorneo);
                }
                return $torneo;
            });

            return response()->json([
                'success' => true,
                'data' => $torneos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los torneos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getActiveTournaments()
    {
        try {
            $torneos = Torneo::whereIn('estado', ['abierto', 'en_progreso'])
                            ->orderBy('fecha_inicio', 'asc')
                            ->get();

            $torneos->transform(function ($torneo) {
                if ($torneo->imagenesTorneo) {
                    $torneo->imagenesTorneo = json_decode($torneo->imagenesTorneo);
                }
                return $torneo;
            });

            return response()->json([
                'success' => true,
                'data' => $torneos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los torneos activos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}