<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\GameController;

// ========================================
// RUTAS PÚBLICAS (sin autenticación)
// ========================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);

// Buscar sesión por código (Espectadores)
Route::get('/sessions/code/{code}', [SessionController::class, 'findByCode']);

// ✅ NUEVO: Login de Moderador (público pero requiere códigos)
Route::post('/moderator/login', [SessionController::class, 'moderatorLogin']);
Route::post('/sessions/moderator-login-verification', [SessionController::class, 'moderatorLoginWithVerification']); // Ambos códigos
Route::post('/sessions/moderator-login-session-code', [SessionController::class, 'moderatorLoginWithSessionCode']); // ← NUEVO: Session Code + Verification
// Rutas públicas para espectadores
Route::prefix('public')->middleware('throttle:300,1')->group(function () {
    Route::get('/sessions/active', [SessionController::class, 'getPublicActiveSessions']);
    Route::get('/sessions/{session}', [SessionController::class, 'getPublicSession']);
    Route::get('/sessions/{session}/games/{status}', [SessionController::class, 'getPublicGamesByStatus']);
    Route::get('/sessions/{session}/players', [SessionController::class, 'getPublicPlayerStats']);
});

// ========================================
// RUTAS AUTENTICADAS
// ========================================

Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
    

        Route::get('/sessions/history', [SessionController::class, 'getUserHistory']);
// En routes/api.php
     // ========================================
    // SESIONES - CRUD Básico
    // ========================================
    Route::prefix('sessions')->group(function () {
        Route::post('/', [SessionController::class, 'store']); // Crear sesión (ahora con soporte para draft)
        Route::get('/active', [SessionController::class, 'activeSessions']); // Sesiones activas
        Route::get('/{session}', [SessionController::class, 'show']); // Ver sesión
        Route::post('/{session}/start', [SessionController::class, 'start']); // Iniciar sesión
        Route::get('/{session}/games/{status}', [SessionController::class, 'getGamesByStatus']);
        Route::get('/{session}/stats', [SessionController::class, 'getPlayerStats']);
    });

    // ========================================
    // ✅ NUEVO: BORRADORES (NEW-DRFT-001)
    // ========================================
    Route::prefix('drafts')->group(function () {
        Route::get('/', [SessionController::class, 'getDrafts']); // Listar borradores
        Route::post('/{session}/activate', [SessionController::class, 'activateDraft']); // Activar borrador
        Route::put('/{session}', [SessionController::class, 'updateDraft']); // Editar borrador
        Route::delete('/{session}', [SessionController::class, 'deleteDraft']); // Eliminar borrador
    });

    // ========================================
    // HISTORIAL Y JUGADORES
    // ========================================
    Route::get('/sessions/history', [SessionController::class, 'getHistory']);
    Route::get('/players/all', [SessionController::class, 'getAllPlayers']);
    Route::get('/players/{player}', [SessionController::class, 'getPlayerDetail']);

    // ========================================
    // AVANCE DE ETAPAS Y PLAYOFFS
    // ========================================
    Route::prefix('sessions/{session}')->group(function () {
        Route::get('/can-advance', [SessionController::class, 'canAdvance']);
        Route::post('/advance-stage', [SessionController::class, 'advanceStage']); // Para Torneos
        Route::post('/advance-to-next-stage', [SessionController::class, 'advanceToNextStage']); // Para P4/P8
        Route::post('/generate-playoff-bracket', [SessionController::class, 'generatePlayoffBracket']);
        Route::post('/generate-p8-finals', [SessionController::class, 'generateP8Finals']);
        Route::post('/finalize', [SessionController::class, 'finalizeSession']);
        Route::post('/auto-generate-finals', [SessionController::class, 'autoGenerateFinalsIfReady']);
        Route::get('/primary-active-game', [GameController::class, 'getPrimaryActiveGame']);
    });

    // ========================================
    // JUEGOS
    // ========================================
    Route::prefix('games')->group(function () {
        Route::post('/{game}/start', [GameController::class, 'start']);
        Route::post('/{game}/score', [GameController::class, 'submitScore']);
        Route::post('/{game}/cancel', [GameController::class, 'cancel']);
        Route::put('/{game}/update-score', [GameController::class, 'updateScore']);
        Route::post('/{game}/skip-to-court', [GameController::class, 'skipToCourt']);
    });

    // ========================================
    // USUARIO Y PERFIL
    // ========================================
    Route::get('/user/profile', [AuthController::class, 'getProfile']);
     Route::put('/user/profile', [AuthController::class, 'updateProfile']); // ← NUEVA

    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
    Route::get('/user/name', [AuthController::class, 'getUserName']);
    Route::post('/user/update-onesignal-id', function (Request $request) {
        $user = Auth::user();
        $playerId = $request->input('onesignal_player_id');

        if (!$playerId) {
            return response()->json(['message' => 'onesignal_player_id es requerido'], 400);
        }

        if ($user->profile) {
            $user->profile->update(['onesignal_player_id' => $playerId]);
            return response()->json(['message' => 'Player ID actualizado con éxito.']);
        }

        return response()->json(['message' => 'Perfil de usuario no encontrado.'], 404);
    });

    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile', [ProfileController::class, 'storeOrUpdate']);
});