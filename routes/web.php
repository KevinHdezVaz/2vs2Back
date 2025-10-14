<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\AffiliateController; // Asegúrate de que la ruta al controlador sea correcta

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Aquí definimos las rutas para el panel de administración web.
|
*/

// --- RUTAS PARA INVITADOS (GUEST) ---
// Este grupo maneja el acceso al panel de administración.
Route::middleware('guest:admin')->group(function () {
    // Muestra el formulario de login del administrador
    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    
    // Procesa el envío del formulario de login del administrador
    Route::post('/login', [SessionsController::class, 'store']);
});

// --- RUTAS PROTEGIDAS PARA ADMINISTRADORES ---
Route::middleware(['auth:admin'])->group(function () {

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        // Apunta a una vista o a otra ruta, como affiliates.index
        return redirect()->route('affiliates.index');
    })->name('dashboard');

    // --- GESTIÓN DE RECURSOS ---
    // Dejé solo esta línea y eliminé la duplicada. Nombres y URL en español.
    Route::resource('usuarios', UserController::class)->names('usuarios');
    Route::resource('planes', PlanController::class)->only(['index', 'edit', 'update'])->names('planes');

    Route::resource('affiliates', AffiliateController::class);
    Route::resource('referrals', ReferralController::class);

    // Ruta para cerrar sesión
    Route::get('/logout', [SessionsController::class, 'destroy'])->name('logout');
});