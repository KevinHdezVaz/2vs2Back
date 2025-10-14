<?php

namespace App\Http\Controllers\API;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class NotificationApiController extends Controller
{
    public function storePlayerId(Request $request)
    {
        \Log::info('Recibiendo player_id request', [
            'data' => $request->all()
        ]);

        try {
            $request->validate([
                'player_id' => 'required|string',
            ]);

            DeviceToken::updateOrCreate(
                ['player_id' => $request->player_id],
                ['player_id' => $request->player_id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Player ID almacenado correctamente'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al guardar player_id', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar player ID',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}