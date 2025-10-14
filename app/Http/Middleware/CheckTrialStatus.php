<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CheckTrialStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Verifica si el usuario está autenticado y si su prueba ha terminado
        if ($user && $user->hasTrialExpired()) {
            
            // Si la petición es AJAX (viene del frontend con fetch o axios)
            if ($request->expectsJson()) {
                return response()->json(['error' => 'trial_expired', 'message' => 'Tu período de prueba ha terminado. Por favor, suscríbete para continuar.'], 403);
            }

            // Si es una petición normal, redirige a una página de pago
            return redirect()->route('subscription.page')->with('warning', 'Tu período de prueba ha terminado.');
        }

        return $next($request);
    }
}
