<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\GameTemplateController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- RUTAS PARA INVITADOS (GUEST) ---
Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    Route::post('/login', [SessionsController::class, 'store']);
});

// --- RUTAS PROTEGIDAS PARA ADMINISTRADORES ---
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    
    Route::get('/dashboard', function () {
        return redirect()->route('affiliates.index');
    })->name('dashboard');

    // --- GESTIÓN DE RECURSOS ---
    Route::resource('usuarios', UserController::class)->names('usuarios');
    Route::resource('planes', PlanController::class)->only(['index', 'edit', 'update'])->names('planes');
    Route::resource('affiliates', AffiliateController::class);
    Route::resource('referrals', ReferralController::class);

    // Ruta para cerrar sesión
    Route::get('/logout', [SessionsController::class, 'destroy'])->name('logout');
});

// --- GESTIÓN DE GAME TEMPLATES (CON AUTENTICACIÓN SIMPLE) ---
// --- GESTIÓN DE GAME TEMPLATES (CON AUTENTICACIÓN SIMPLE) ---
Route::prefix('templates')->name('templates.')->group(function () {
    
    // ✅ Login SIN middleware (público)
    Route::get('/login', [GameTemplateController::class, 'showLogin'])->name('login');
    Route::post('/login', [GameTemplateController::class, 'login'])->name('login.post');
    
    // ✅ Rutas protegidas CON middleware
    Route::middleware('simple.auth')->group(function () {
        Route::get('/', [GameTemplateController::class, 'index'])->name('index');
        Route::post('/upload', [GameTemplateController::class, 'upload'])->name('upload');
        Route::get('/logout', [GameTemplateController::class, 'logout'])->name('logout');
        
        // ✅ NUEVO: Ruta para renombrar
        Route::post('/{filename}/rename', [GameTemplateController::class, 'rename'])->name('rename');
        
        Route::get('/{filename}/download', [GameTemplateController::class, 'download'])->name('download');
        Route::delete('/{filename}', [GameTemplateController::class, 'delete'])->name('delete');
        Route::get('/{filename}', [GameTemplateController::class, 'view'])->name('view');
    });
});