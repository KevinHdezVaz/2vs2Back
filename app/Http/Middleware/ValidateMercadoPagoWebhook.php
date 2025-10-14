<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateMercadoPagoWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $signatureHeader = $request->header('X-Signature');
        $webhookSecret = config('services.mercadopago.webhook_secret');

        // Verificar que tenemos la firma y el secreto
        if (!$signatureHeader || !$webhookSecret) {
            Log::error('Missing webhook signature or secret');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Extraer el valor de `v1` de la firma
        $signatureParts = explode(',', $signatureHeader);
        $signatureData = [];
        foreach ($signatureParts as $part) {
            list($key, $value) = explode('=', $part);
            $signatureData[$key] = $value;
        }

        if (!isset($signatureData['v1'])) {
            Log::error('Invalid signature format: v1 not found');
            return response()->json(['error' => 'Invalid signature format'], 401);
        }

        $receivedSignature = $signatureData['v1'];

        // Generar la firma esperada
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        // Verificar que las firmas coinciden
        if (!hash_equals($receivedSignature, $expectedSignature)) {
            Log::error('Invalid webhook signature', [
                'expected' => $expectedSignature,
                'received' => $receivedSignature
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::info('Webhook signature validated successfully');
        return $next($request);
    }
}