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

Route::get('/sessions/code/{code}', [SessionController::class, 'findByCode']);

Route::prefix('public')->middleware('throttle:300,1')->group(function () {
    Route::get('/sessions/active', [SessionController::class, 'getPublicActiveSessions']);
    Route::get('/sessions/{session}', [SessionController::class, 'getPublicSession']);
    Route::get('/sessions/{session}/games/{status}', [SessionController::class, 'getPublicGamesByStatus']);
    Route::get('/sessions/{session}/players', [SessionController::class, 'getPublicPlayerStats']);
});


// Rutas PÚBLICAS para espectadores
Route::prefix('public')->middleware('throttle:300,1')->group(function () {
    Route::get('/sessions/active', [SessionController::class, 'getPublicActiveSessions']);
    Route::get('/sessions/{session}', [SessionController::class, 'getPublicSession']);
    Route::get('/sessions/{session}/games/{status}', [SessionController::class, 'getPublicGamesByStatus']);
    Route::get('/sessions/{session}/players', [SessionController::class, 'getPublicPlayerStats']);
});

Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
    // HISTORY Y PLAYERS
    Route::get('/sessions/history', [SessionController::class, 'getHistory']);
    Route::get('/players/all', [SessionController::class, 'getAllPlayers']);
    Route::get('/players/{player}', [SessionController::class, 'getPlayerDetail']);


        Route::post('/sessions/{session}/auto-generate-finals', [SessionController::class, 'autoGenerateFinalsIfReady']);

        
    // ✅ Rutas para avanzar entre stages
    Route::prefix('sessions/{session}')->group(function () {
        Route::get('/can-advance', [SessionController::class, 'canAdvance']);
        Route::post('/advance-stage', [SessionController::class, 'advanceStage']); // Para Torneos
        Route::post('/advance-to-next-stage', [SessionController::class, 'advanceToNextStage']); // Para P4/P8
        Route::post('/generate-playoff-bracket', [SessionController::class, 'generatePlayoffBracket']);
        Route::post('/generate-p8-finals', [SessionController::class, 'generateP8Finals']);
        Route::post('/finalize', [SessionController::class, 'finalizeSession']);
        Route::get('/primary-active-game', [GameController::class, 'getPrimaryActiveGame']);
    });

    // Rutas de sesiones
    Route::prefix('sessions')->group(function () {
        Route::post('/', [SessionController::class, 'store']);
        Route::get('/active', [SessionController::class, 'activeSessions']);
        Route::get('/{session}', [SessionController::class, 'show']);
        Route::post('/{session}/start', [SessionController::class, 'start']);
        Route::get('/{session}/games/{status}', [SessionController::class, 'getGamesByStatus']);
        Route::get('/{session}/stats', [SessionController::class, 'getPlayerStats']);
    });


     Route::get('/user/profile', [AuthController::class, 'getProfile']);
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
    
    // Rutas de juegos
    Route::put('games/{game}/update-score', [GameController::class, 'updateScore']);
    Route::post('games/{game}/skip-to-court', [GameController::class, 'skipToCourt']);

    Route::prefix('games')->group(function () {
        Route::post('/{game}/start', [GameController::class, 'start']);
        Route::post('/{game}/score', [GameController::class, 'submitScore']);
        Route::post('/{game}/cancel', [GameController::class, 'cancel']);
    });

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