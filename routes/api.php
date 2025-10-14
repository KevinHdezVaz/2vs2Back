<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\GameController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);

// Rutas PÚBLICAS para espectadores (sin middleware auth:sanctum)
Route::prefix('public')->middleware('throttle:300,1')->group(function () {
    Route::get('/sessions/active', [SessionController::class, 'getPublicActiveSessions']);
    Route::get('/sessions/{session}', [SessionController::class, 'getPublicSession']);
    Route::get('/sessions/{session}/games/{status}', [SessionController::class, 'getPublicGamesByStatus']);
    Route::get('/sessions/{session}/players', [SessionController::class, 'getPublicPlayerStats']);
});

// Rutas de Google
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('login/google', [AuthController::class, 'loginWithGoogle']);

Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {


    // HISTORY Y PLAYERS - Deben ir ANTES de las rutas con parámetros dinámicos
    Route::get('/sessions/history', [SessionController::class, 'getHistory']);
    Route::get('/players/all', [SessionController::class, 'getAllPlayers']);
    Route::get('/players/{player}', [SessionController::class, 'getPlayerDetail']);

    // Rutas para avanzar entre stages

    // Rutas para avanzar entre stages
Route::prefix('sessions/{session}')->group(function () {
    // Verificar si se puede avanzar
    Route::get('/can-advance', [SessionController::class, 'canAdvance']);
    
    // Avanzar al siguiente stage (PARA TORNEOS)
    Route::post('/advance-stage', [SessionController::class, 'advanceStage']);
    
    // Generar bracket de playoffs (PARA P4/P8)  
    Route::post('/generate-playoff-bracket', [SessionController::class, 'generatePlayoffBracket']);
});

Route::post('/sessions/{session}/generate-p8-finals', [SessionController::class, 'generateP8Finals'])->middleware('auth:sanctum');

    // routes/api.php
Route::post('/sessions/{session}/advance-stage', [SessionController::class, 'advanceToNextStage']);

    Route::prefix('sessions')->group(function () {
        Route::post('/', [SessionController::class, 'store']); // Crear sesión
        Route::get('/active', [SessionController::class, 'activeSessions']); // Sesiones activas

        // Rutas con {session} van después de las específicas
        Route::get('/{session}', [SessionController::class, 'show']); // Ver sesión
        Route::post('/{session}/start', [SessionController::class, 'start']); // Iniciar sesión
        Route::post('/{session}/advance-stage', [SessionController::class, 'advanceStage']); // Avanzar stage
        Route::post('/{session}/generate-playoff-bracket', [SessionController::class, 'generatePlayoffBracket']);

        // Juegos de una sesión
        Route::get('/{session}/games/{status}', [SessionController::class, 'getGamesByStatus']); // live, pending, completed
        Route::get('/{session}/stats', [SessionController::class, 'getPlayerStats']); // Estadísticas
    });

    // Rutas de juegos
    Route::put('games/{game}/update-score', [GameController::class, 'updateScore']);
    Route::post('games/{game}/skip-to-court', [GameController::class, 'skipToCourt']);

    Route::prefix('games')->group(function () {
        Route::post('/{game}/start', [GameController::class, 'start']); // Iniciar juego
        Route::post('/{game}/score', [GameController::class, 'submitScore']); // Registrar score
        Route::post('/{game}/cancel', [GameController::class, 'cancel']); // Cancelar juego
    });

    Route::get('/user/name', [AuthController::class, 'getUserName']);

    // Ruta para actualizar el OneSignal Player ID
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
