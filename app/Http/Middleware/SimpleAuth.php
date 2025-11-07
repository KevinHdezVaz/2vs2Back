<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SimpleAuth
{
    /**
     * Credenciales hardcodeadas (cámbialas después)
     */
    private const USERNAME = 'admin';
    private const PASSWORD = 'picklebracket2024';

    public function handle(Request $request, Closure $next)
    {
        // ✅ PERMITIR acceso a la ruta de login (GET y POST)
        if ($request->is('templates/login')) {
            // Si es POST, validar credenciales
            if ($request->isMethod('post')) {
                if ($request->input('username') === self::USERNAME && 
                    $request->input('password') === self::PASSWORD) {
                    session(['authenticated' => true]);
                    return redirect()->route('templates.index');
                }
                // ✅ IMPORTANTE: Redirigir con error
                return redirect()->back()->withErrors(['error' => 'Usuario o contraseña incorrectos']);
            }
            // Si es GET, mostrar el formulario (pasar al siguiente middleware)
            return $next($request);
        }

        // ✅ Para todas las demás rutas, verificar autenticación
        if (session('authenticated') !== true) {
            return redirect()->route('templates.login')->withErrors(['error' => 'Debes iniciar sesión primero']);
        }

        return $next($request);
    }
}