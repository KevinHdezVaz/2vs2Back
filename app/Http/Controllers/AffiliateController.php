<?php

namespace App\Http\Controllers; // O el namespace que estés usando

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // <-- Importante: Añade el Facade de Log

class AffiliateController extends Controller
{
    public function validateCode(Request $request)
    {
        Log::info('================ INICIO VALIDACIÓN DE CÓDIGO AFILIADO ================');
        Log::info('Request recibido:', $request->all());

        $validated = $request->validate([
            'code' => 'required|string',
        ]);
        
        $codeToValidate = $validated['code'];
        Log::info("Buscando afiliado con código: {$codeToValidate} y estado 'active'");

        $affiliate = Affiliate::where('referral_code', $codeToValidate)
                              ->where('status', 'active')
                              ->first();

        if (!$affiliate) {
            Log::warning("No se encontró ningún afiliado activo con el código: {$codeToValidate}");
            Log::info('================ FIN VALIDACIÓN DE CÓDIGO AFILIADO ================');
            
            return response()->json([
                'valid' => false,
                'message' => 'El código de afiliado no es válido o ha expirado.',
            ], 404);
        }

        Log::info("¡Éxito! Afiliado encontrado:", ['id' => $affiliate->id, 'name' => $affiliate->name]);
        Log::info('================ FIN VALIDACIÓN DE CÓDIGO AFILIADO ================');

        return response()->json([
            'valid' => true,
            'discount_percentage' => $affiliate->discount_percentage,
        ]);
    }
}