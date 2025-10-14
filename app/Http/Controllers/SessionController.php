<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\Player;
use App\Models\Session;
use Illuminate\Http\Request;
use App\Services\RatingService;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\GameGeneratorService;

class SessionController extends Controller
{
    public function __construct(
          private GameGeneratorService $gameGenerator,
    private RatingService $ratingService
    ) {}

    /**
     * Crear nueva sesión
     */
    

   public function store(Request $request): JsonResponse
{
    \Log::info('Request data received:', $request->all());
    
    try {
        $validated = $request->validate([
            'session_name' => 'required|string|max:255',
            'number_of_courts' => 'required|integer|min:1|max:4',
            'duration_hours' => 'required|integer|min:1|max:3',
            'number_of_players' => 'required|integer',
            'points_per_game' => 'required|integer|in:7,11,15,21',
            'win_by' => 'required|integer|in:1,2',
            'number_of_sets' => 'required|string',
            'session_type' => 'required|string|in:T,P4,P8',
            'courts' => 'required|array',
            'courts.*.court_name' => 'required|string',
            'players' => 'required|array',
            'players.*.first_name' => 'required|string',
            'players.*.last_initial' => 'required|string|max:50',
            'players.*.level' => 'nullable|string',
            'players.*.dominant_hand' => 'nullable|string',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Validation error:', [
            'errors' => $e->errors(),
            'message' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);
    }
    
    \Log::info('Data validated:', $validated);

    // ✅ VALIDATION: Create temporary session to validate configuration
    $tempSession = new Session([
        'number_of_courts' => $validated['number_of_courts'],
        'duration_hours' => $validated['duration_hours'],
        'number_of_players' => $validated['number_of_players'],
        'session_type' => $validated['session_type'],
    ]);

    $validation = $this->gameGenerator->validateSessionConfiguration($tempSession);
    
    if (!$validation['valid']) {
        \Log::warning('Configuration validation failed:', ['message' => $validation['message']]);
        return response()->json([
            'message' => $validation['message']
        ], 422);
    }

    // Validate player count based on courts
    $minPlayers = $validated['number_of_courts'] * 4;
    $maxPlayers = $validated['number_of_courts'] * 8;
    
    if ($validated['number_of_players'] < $minPlayers || 
        $validated['number_of_players'] > $maxPlayers) {
        return response()->json([
            'message' => "Number of players must be between {$minPlayers} and {$maxPlayers}"
        ], 422);
    }

    // Create in MySQL
    $session = Session::create([
        'firebase_id' => uniqid('session_'),
        'user_id' => $request->user()->id,
        'session_name' => $validated['session_name'],
        'number_of_courts' => $validated['number_of_courts'],
        'duration_hours' => $validated['duration_hours'],
        'number_of_players' => $validated['number_of_players'],
        'points_per_game' => $validated['points_per_game'],
        'win_by' => $validated['win_by'],
        'number_of_sets' => $validated['number_of_sets'],
        'session_type' => $validated['session_type'],
        'status' => 'pending'
    ]);

    // Create courts
    foreach ($validated['courts'] as $index => $courtData) {
        Court::create([
            'session_id' => $session->id,
            'court_name' => $courtData['court_name'],
            'court_number' => $index + 1
        ]);
    }

    // Create players
    foreach ($validated['players'] as $playerData) {
        $player = Player::create([
            'session_id' => $session->id,
            'first_name' => $playerData['first_name'],
            'last_initial' => strtoupper($playerData['last_initial']),
            'level' => $playerData['level'] ?? 'Average',
            'dominant_hand' => $playerData['dominant_hand'] ?? 'None'
        ]);

        $player->initial_rating = $player->getInitialRatingByLevel();
        $player->current_rating = $player->initial_rating;
        $player->save();
    }

    \Log::info('Session created successfully:', ['session_id' => $session->id]);

    return response()->json([
        'message' => 'Session created successfully',
        'session' => $session->load(['courts', 'players'])
    ], 201);
}

public function getHistory(Request $request): JsonResponse
{
    $sessions = Session::where('user_id', $request->user()->id)
        ->where('status', 'completed')
        ->with(['courts', 'players' => function($query) {
            $query->orderBy('current_rank');
        }])
        ->orderBy('completed_at', 'desc')
        ->get()
        ->map(function($session) {
            // Obtener el ganador (jugador con rank 1)
            $winner = $session->players->where('current_rank', 1)->first();
            
            // Calcular duración en minutos
            $durationMinutes = null;
            if ($session->started_at && $session->completed_at) {
                $durationMinutes = $session->started_at->diffInMinutes($session->completed_at);
            }
            
            return [
                'id' => $session->id,
                'session_name' => $session->session_name,
                'session_type' => $session->session_type,
                'number_of_players' => $session->number_of_players,
                'number_of_courts' => $session->number_of_courts,
                'duration_minutes' => $durationMinutes, // ← En minutos
                'started_at' => $session->started_at?->toIso8601String(),
                'completed_at' => $session->completed_at?->toIso8601String(),
                'total_games' => $session->games()->count(),
                'winner' => $winner ? [
                    'id' => $winner->id,
                    'display_name' => $winner->display_name,
                    'current_rating' => round($winner->current_rating, 0),
                ] : null,
            ];
        });

    return response()->json(['sessions' => $sessions]);
}
/**
 * Obtener todos los jugadores que han participado en sesiones del usuario
 */
public function getAllPlayers(Request $request): JsonResponse
{
    $players = Player::whereHas('session', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
        ->with('session:id,session_name,session_type,completed_at')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($player) {
            return [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'level' => $player->level,
                'dominant_hand' => $player->dominant_hand,
                'games_played' => $player->games_played,
                'games_won' => $player->games_won,
                'win_percentage' => round($player->win_percentage, 1),
                'current_rating' => round($player->current_rating, 0),
                'session' => [
                    'name' => $player->session->session_name,
                    'type' => $player->session->session_type,
                ],
            ];
        });

    return response()->json(['players' => $players]);
}

/**
 * Obtener detalle de un jugador específico
 */
public function getPlayerDetail(Player $player): JsonResponse
{
    // Verificar que el jugador pertenece a una sesión del usuario autenticado
    if ($player->session->user_id !== auth()->id()) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    return response()->json([
        'player' => $player->load('session'),
        'games' => $player->session->games()
            ->where(function($query) use ($player) {
                $query->where('team1_player1_id', $player->id)
                    ->orWhere('team1_player2_id', $player->id)
                    ->orWhere('team2_player1_id', $player->id)
                    ->orWhere('team2_player2_id', $player->id);
            })
            ->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2'])
            ->orderBy('game_number')
            ->get()
    ]);
}



/**
 * Generar bracket de playoffs
 */


public function generatePlayoffBracket(Session $session): JsonResponse
{
    if (!$session->isPlayoff4() && !$session->isPlayoff8()) {
        return response()->json([
            'message' => 'Only Playoff 4 or 8 sessions can generate brackets'
        ], 422);
    }

    // Verificar que no haya juegos de playoff ya generados
    $hasPlayoffGames = $session->games()
        ->where('is_playoff_game', true)
        ->exists();

    if ($hasPlayoffGames) {
        return response()->json([
            'message' => 'Playoff bracket has already been generated'
        ], 422);
    }

    // Generar bracket
    $games = $this->gameGenerator->generatePlayoffBracket($session);

    // Asignar canchas a los primeros juegos
    $courts = $session->courts;
    foreach ($games->take($courts->count()) as $index => $game) {
        if (isset($courts[$index])) {
            $game->court_id = $courts[$index]->id;
            $game->save();
        }
    }

    // ✅ ACTUALIZAR PROGRESO después de generar playoffs
    $session->updateProgress();

    return response()->json([
        'message' => 'Bracket generated successfully',
        'games' => $games
    ]);
}

/**
 * Obtener sesiones activas (público)
 */
public function getPublicActiveSessions(): JsonResponse
{
    $sessions = Session::where('status', 'active')
        ->with(['courts'])
        ->get();

    return response()->json(['sessions' => $sessions]);
}

/**
 * Obtener sesión (público)
 */
public function getPublicSession(Session $session): JsonResponse
{
    $elapsedSeconds = $session->started_at 
        ? now()->diffInSeconds($session->started_at) 
        : 0;

    return response()->json([
        'session' => $session->load([
            'courts',
            'players' => function($query) {
                $query->orderBy('current_rank');
            },
        ]),
        'elapsed_seconds' => $elapsedSeconds  // 👈 Agregar esto
    ]);
}

/**
 * Obtener juegos por estado (público)
 */
public function getPublicGamesByStatus(Session $session, string $status): JsonResponse
{
    $games = $session->games()
        ->where('status', $status)
        ->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
        ->get();

    return response()->json(['games' => $games]);
}

/**
 * Obtener estadísticas de jugadores (público)
 */
public function getPublicPlayerStats(Session $session): JsonResponse
{
    $players = $session->players()
        ->orderBy('current_rank')
        ->get();

    return response()->json(['players' => $players]);
}

    /** 
     * Obtener detalles de sesión
     */
    public function show(Session $session): JsonResponse
{
    $elapsedSeconds = $session->started_at 
        ? now()->diffInSeconds($session->started_at) 
        : 0;

    return response()->json([
        'session' => $session->load([
            'courts',
            'players' => function($query) {
                $query->orderBy('current_rank');
            },
            'games' => function($query) {
                $query->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court']);
            }
        ]),
        'elapsed_seconds' => $elapsedSeconds  // 👈 Agregar esto
    ]);
}
  /**
 * Iniciar sesión (generar juegos iniciales)
 */
 

public function start(Session $session): JsonResponse
{
    if ($session->status !== 'pending') {
        return response()->json([
            'message' => 'La sesión ya fue iniciada'
        ], 422);
    }

    // Generar juegos según tipo de sesión
    $games = $this->gameGenerator->generateInitialGames($session);

    $session->status = 'active';
    $session->started_at = now();
    $session->save();

    // COMENTAR FIREBASE (líneas 141-145)
    // $this->firebaseService->updateSession($session->firebase_id, [
    //     'status' => 'active',
    //     'started_at' => $session->started_at->toIso8601String()
    // ]);

    return response()->json([
        'message' => 'Sesión iniciada exitosamente',
        'games' => $games
    ]);
}

    /**
     * Obtener juegos por estado
     */
    public function getGamesByStatus(Session $session, string $status): JsonResponse
    {
        $games = $session->games()
            ->where('status', $status)
            ->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
            ->get();

        return response()->json(['games' => $games]);
    }

    /**
     * Obtener estadísticas de jugadores
     */
    public function getPlayerStats(Session $session): JsonResponse
    {
        $players = $session->players()
            ->orderBy('current_rank')
            ->get();

        return response()->json(['players' => $players]);
    }

    /**
     * Avanzar al siguiente stage (solo para Tournament)
     */

    /**
 * Verificar si se puede avanzar al siguiente stage
 */
public function canAdvance(Session $session): JsonResponse
{

      // ✅ NUEVA VALIDACIÓN: No avanzar si ya hay juegos de playoff
    if ($session->isPlayoff4() || $session->isPlayoff8()) {
        $hasPlayoffGames = $session->games()
            ->where('is_playoff_game', true)
            ->exists();
            
        if ($hasPlayoffGames) {
            return response()->json([
                'can_advance' => false,
                'message' => 'Playoff bracket already generated - cannot advance further'
            ]);
        }
    }
    

    // Verificar que la sesión pertenezca al usuario
    if ($session->user_id !== auth()->id()) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    $canAdvance = $session->canAdvanceStage();
    
    $pendingGames = $session->games()
        ->where('status', '!=', 'completed')
        ->count();

    $currentStageGames = $session->games()
        ->where('stage', $session->current_stage)
        ->where('status', '!=', 'completed')
        ->count();

    return response()->json([
        'can_advance' => $canAdvance,
        'current_stage' => $session->current_stage,
        'session_type' => $session->session_type,
        'total_pending_games' => $pendingGames,
        'current_stage_pending_games' => $currentStageGames,
        'has_playoff_games' => $session->games()->where('is_playoff_game', true)->exists(),
        'message' => $canAdvance 
            ? 'Ready to advance to next stage' 
            : "Cannot advance - {$currentStageGames} games pending in current stage"
    ]);
}

/**
 * Avanzar al siguiente stage (para torneos)
 */
public function advanceStage(Session $session): JsonResponse
{
    // Verificar autorización
    if ($session->user_id !== auth()->id()) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    // Validar que se puede avanzar
    if (!$session->canAdvanceStage()) {
        $pendingGames = $session->games()
            ->where('status', '!=', 'completed')
            ->count();
            
        return response()->json([
            'message' => "Cannot advance. There are {$pendingGames} pending games."
        ], 422);
    }

    // Solo para torneos
    if (!$session->isTournament()) {
        return response()->json([
            'message' => 'Only Tournament sessions can advance stages'
        ], 422);
    }

    if ($session->current_stage >= 3) {
        return response()->json([
            'message' => 'All tournament stages have been completed'
        ], 422);
    }

    $oldStage = $session->current_stage;
    $session->current_stage++;
    $session->save();

    // Limpiar cola de partidos anteriores
    $this->clearPendingGames($session);

    // Generar juegos del nuevo stage
    $games = $this->gameGenerator->generateStageGames($session);


     $session->updateRankings();

  
    Log::info('Tournament stage advanced', [
        'session_id' => $session->id,
        'from_stage' => $oldStage,
        'to_stage' => $session->current_stage,
        'games_generated' => $games->count()
    ]);

    return response()->json([
        'message' => "Advanced to Stage {$session->current_stage}",
        'stage' => $session->current_stage,
        'games_generated' => $games->count(),
        'warning' => 'This action is irreversible. All previous stage games have been cleared from the queue.'
    ]);
}

/**
 * Limpiar todos los juegos pendientes de la cola
 */
private function clearPendingGames(Session $session): void
{
    $pendingGames = $session->games()
        ->where('status', 'pending')
        ->get();

    foreach ($pendingGames as $game) {
        $game->court_id = null; // Liberar cancha
        $game->save();
    }

    Log::info('Pending games cleared from queue', [
        'session_id' => $session->id,
        'games_cleared' => $pendingGames->count()
    ]);
}




/**
 * Avanzar al siguiente stage con validación completa
 */
/**
 * Avanzar al siguiente stage con validación completa
 */
public function advanceToNextStage(Request $request, Session $session): JsonResponse
{
    try {
        // Validar que la sesión es P4/P8
        if (!$session->isPlayoff4() && !$session->isPlayoff8()) {
            return response()->json([
                'success' => false,
                'error' => 'Solo sesiones P4/P8 pueden avanzar etapas manualmente'
            ], 400);
        }

        // ✅ Obtener juegos pendientes
        $pendingGames = $session->games()->where('status', 'pending')->count();
        $completedGames = $session->games()->where('status', 'completed')->count();

        Log::info('Iniciando avance a siguiente etapa', [
            'session_id' => $session->id,
            'session_type' => $session->session_type,
            'pending_games' => $pendingGames,
            'completed_games' => $completedGames
        ]);

        // ✅ Limpiar juegos pendientes (si los hay)
        $deletedCount = 0;
        if ($pendingGames > 0) {
            $pendingGamesToDelete = $session->games()->where('status', 'pending')->get();
            $deletedCount = $pendingGamesToDelete->count();
            
            foreach ($pendingGamesToDelete as $game) {
                $game->delete();
            }

            Log::info('Juegos pendientes eliminados al avanzar etapa', [
                'session_id' => $session->id,
                'pending_deleted' => $deletedCount
            ]);
        }

        // ✅ Generar siguiente etapa
        $newGames = $this->gameGenerator->generateNextStageGames($session);

        if ($newGames->isEmpty()) {
            Log::warning('No se generaron nuevos juegos al avanzar etapa', [
                'session_id' => $session->id,
                'session_type' => $session->session_type
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'No hay más etapas disponibles para avanzar'
            ], 400);
        }

        // ✅ 🏟️ ASIGNAR CANCHAS CORRECTAMENTE - SOLO A LOS PRIMEROS JUEGOS
        $courts = $session->courts;
        $gamesWithCourts = 0;
        
        foreach ($newGames->take($courts->count()) as $index => $game) {
            if (isset($courts[$index])) {
                $game->court_id = $courts[$index]->id;
                $game->save();
                $gamesWithCourts++;
                
                Log::debug('Cancha asignada a juego de playoff', [
                    'game_id' => $game->id,
                    'court_id' => $game->court_id,
                    'playoff_round' => $game->playoff_round
                ]);
            }
        }
        
        // ✅ Los juegos restantes quedan SIN cancha (en cola)
        foreach ($newGames->skip($courts->count()) as $game) {
            $game->court_id = null;
            $game->save();
        }

        // ✅ Actualizar progreso de la sesión
        $session->updateProgress();

        Log::info('Avance a siguiente etapa completado exitosamente', [
            'session_id' => $session->id,
            'new_games_count' => $newGames->count(),
            'games_with_courts' => $gamesWithCourts,
            'games_in_queue' => $newGames->count() - $gamesWithCourts,
            'previous_pending_deleted' => $deletedCount,
            'total_games_now' => $session->games()->count()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Playoff bracket generated successfully!',
            'new_games_count' => $newGames->count(),
            'games_with_courts' => $gamesWithCourts,
            'games_in_queue' => $newGames->count() - $gamesWithCourts,
            'previous_pending_deleted' => $deletedCount,
            'total_games' => $session->games()->count()
        ]);

    } catch (\Exception $e) {
        Log::error('Error advancing to next stage', [
            'session_id' => $session->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to advance stage: ' . $e->getMessage()
        ], 500);
    }
}


/**
 * Generar finals de P8 manualmente
 */
public function generateP8Finals(Session $session): JsonResponse
{
    if ($session->user_id !== auth()->id()) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    if (!$session->isPlayoff8()) {
        return response()->json([
            'message' => 'Only P8 sessions can generate finals'
        ], 422);
    }

    // Verificar que las semifinals estén completadas
    $semifinals = $session->games()
        ->where('is_playoff_game', true)
        ->where('playoff_round', 'semifinal')
        ->get();

    if ($semifinals->count() !== 2) {
        return response()->json([
            'message' => 'Both semifinals must exist'
        ], 422);
    }

    if ($semifinals->where('status', 'completed')->count() !== 2) {
        return response()->json([
            'message' => 'Both semifinals must be completed'
        ], 422);
    }

    // Verificar que no existan finals ya
    $existingFinals = $session->games()
        ->where('is_playoff_game', true)
        ->whereIn('playoff_round', ['gold', 'bronze'])
        ->exists();

    if ($existingFinals) {
        return response()->json([
            'message' => 'Finals have already been generated'
        ], 422);
    }

    // Generar finals
    $games = $this->gameGenerator->generateP8Finals($session, $semifinals);

    return response()->json([
        'message' => 'Finals generated successfully',
        'games' => $games->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2'])
    ]);
}


public function generateNextStageGames(Session $session): Collection
{
 

    $template = $this->loadTemplate($session);
    
    // ✅ Determinar siguiente bloque BASADO EN LO QUE YA SE GENERÓ
    $nextBlockIndex = $this->getNextBlockIndex($session, $template);
    
    if ($nextBlockIndex === null) {
        Log::info('No hay más bloques para generar', [
            'session_id' => $session->id
        ]);
        return collect();
    }
    
    // ✅ Marcar este bloque como generado
    $this->markBlockAsGenerated($session, $nextBlockIndex);


    if (!$template) {
        Log::error('No template found for generating next stage', [
            'session_id' => $session->id,
            'session_type' => $session->session_type
        ]);
        return collect();
    }

    $players = $session->players->keyBy('id')->values();
    $games = collect();
    
    // Obtener último game_number
    $lastGame = $session->games()->orderBy('game_number', 'desc')->first();
    $gameNumber = $lastGame ? $lastGame->game_number + 1 : 1;

    // ✅ Determinar qué bloque generar basado en juegos completados
    $completedGames = $session->games()->where('status', 'completed')->count();
    $currentBlockIndex = $this->getNextBlockIndex($template, $completedGames);

    Log::info('Generando siguiente etapa', [
        'session_id' => $session->id,
        'current_block_index' => $currentBlockIndex,
        'completed_games' => $completedGames
    ]);

    // ✅ Generar el BLOQUE CORRESPONDIENTE
    if (isset($template['blocks'][$currentBlockIndex])) {
        $block = $template['blocks'][$currentBlockIndex];
        
        foreach ($block['rounds'] as $round) {
            foreach ($round['courts'] as $courtData) {
                $teamA = $courtData['A'];
                $teamB = $courtData['B'];

                // Para playoffs, usar jugadores rankeados
                $team1Player1 = $this->getPlayerFromAdvancedNotation($teamA[0], $players, $session);
                $team1Player2 = $this->getPlayerFromAdvancedNotation($teamA[1], $players, $session);
                $team2Player1 = $this->getPlayerFromAdvancedNotation($teamB[0], $players, $session);
                $team2Player2 = $this->getPlayerFromAdvancedNotation($teamB[1], $players, $session);

                if (!$team1Player1 || !$team1Player2 || !$team2Player1 || !$team2Player2) {
                    Log::warning('No se pudieron obtener jugadores para el juego', [
                        'teamA' => $teamA,
                        'teamB' => $teamB,
                        'block' => $block['label']
                    ]);
                    continue;
                }

                $game = Game::create([
                    'session_id' => $session->id,
                    'game_number' => $gameNumber,
                    'stage' => null,
                    'status' => 'pending',
                    'team1_player1_id' => $team1Player1->id,
                    'team1_player2_id' => $team1Player2->id,
                    'team2_player1_id' => $team2Player1->id,
                    'team2_player2_id' => $team2Player2->id,
                    'is_playoff_game' => true,
                    'playoff_round' => $this->getPlayoffRoundFromLabel($block['label'])
                ]);

                $games->push($game);
                $gameNumber++;
                
                Log::info('Juego de playoff generado', [
                    'game_number' => $gameNumber - 1,
                    'playoff_round' => $game->playoff_round,
                    'team1' => [$team1Player1->display_name, $team1Player2->display_name],
                    'team2' => [$team2Player1->display_name, $team2Player2->display_name]
                ]);
            }
        }
    }

    $this->assignCourtsToGames($session, $games);
    
    Log::info('Siguiente etapa generada exitosamente', [
        'session_id' => $session->id,
        'games_created' => $games->count(),
        'next_block_index' => $currentBlockIndex
    ]);

    return $games;
}

// ✅ MÉTODO AUXILIAR: Determinar siguiente bloque
private function getNextBlockIndex(array $template, int $completedGames): int
{
    // Para P4/P8, siempre generar el bloque 1 (segundo bloque) después del primero
    return 1; // Semifinals/Finals
}

// ✅ MÉTODO AUXILIAR: Obtener round de playoff
private function getPlayoffRoundFromLabel(string $label): string
{
    if (stripos($label, 'Semi') !== false) return 'semifinal';
    if (stripos($label, 'Final') !== false) return 'final';
    if (stripos($label, 'Medal') !== false) return 'medal';
    if (stripos($label, 'Bronze') !== false) return 'bronze';
    if (stripos($label, 'Gold') !== false) return 'gold';
    return 'playoff';
}
/**
 * Avanzar stage para torneos (Stage 1 → 2 → 3)
 */
private function advanceTournamentStage(Session $session): JsonResponse
{
    if ($session->current_stage >= 3) {
        return response()->json([
            'message' => 'All tournament stages have been completed'
        ], 422);
    }

    $oldStage = $session->current_stage;
    $session->current_stage++;
    $session->save();

    // Limpiar cola de partidos anteriores
    $this->clearPendingGames($session);

    // Generar juegos del nuevo stage
    $games = $this->gameGenerator->generateStageGames($session);

    Log::info('Tournament stage advanced', [
        'session_id' => $session->id,
        'from_stage' => $oldStage,
        'to_stage' => $session->current_stage,
        'games_generated' => $games->count()
    ]);

    return response()->json([
        'message' => "Advanced to Stage {$session->current_stage}",
        'stage' => $session->current_stage,
        'games_generated' => $games->count(),
        'warning' => 'This action is irreversible. All previous stage games have been cleared from the queue.'
    ]);
}

/**
 * Avanzar a finals para playoffs
 */
private function advanceToPlayoffFinals(Session $session): JsonResponse
{
    // Verificar que no haya playoffs ya generados
    $hasPlayoffGames = $session->games()
        ->where('is_playoff_game', true)
        ->exists();

    if ($hasPlayoffGames) {
        return response()->json([
            'message' => 'Playoff finals have already been generated'
        ], 422);
    }

    // Limpiar cola de partidos de la fase regular
    $this->clearPendingGames($session);

    // Generar bracket de playoffs
    $games = $this->gameGenerator->generatePlayoffBracket($session);

    Log::info('Playoff finals generated', [
        'session_id' => $session->id,
        'playoff_type' => $session->session_type,
        'games_generated' => $games->count()
    ]);

    return response()->json([
        'message' => 'Playoff finals generated',
        'games_generated' => $games->count(),
        'warning' => 'This action is irreversible. All regular games have been cleared from the queue.'
    ]);
}

/**
 * Validar si se puede avanzar de stage
 */
private function canAdvanceStage(Session $session): bool
{
    // Para torneos: validar por stage
    if ($session->isTournament()) {
        return $session->games()
            ->where('stage', $session->current_stage)
            ->where('status', '!=', 'completed')
            ->count() === 0;
    }
    
    // Para playoffs: validar que todos los juegos regulares estén completos
    if ($session->isPlayoff4() || $session->isPlayoff8()) {
        return $session->games()
            ->where('is_playoff_game', false)
            ->where('status', '!=', 'completed')
            ->count() === 0;
    }
    
    return false;
}
 


    /**
     * Listar sesiones activas del usuario
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $sessions = Session::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->with('courts', 'players')
            ->get();

        return response()->json(['sessions' => $sessions]);
    }
}