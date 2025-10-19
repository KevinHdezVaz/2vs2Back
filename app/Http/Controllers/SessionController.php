<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\Player;
use App\Models\Session;
use Illuminate\Http\Request;
use App\Services\RatingService;
use App\Models\Game;

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
            'session_type' => 'required|string|in:T,P4,P8,O',
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
        'session_code' => Session::generateUniqueCode(), // ✅ AGREGAR
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

    \Log::info('Session created, now generating games', ['session_id' => $session->id]);

    // ✅ GENERAR JUEGOS INICIALES
    try {
        $games = collect();
        
        if ($session->isPlayoff4() || $session->isPlayoff8()) {
            // Para P4/P8: generar fase regular
             

            // ✅ CORRECTO
     $games = $this->gameGenerator->generateAllGames($session); // ← USA ESTE

            Log::info('Regular phase games generated', [
                'session_id' => $session->id,
                'session_type' => $session->session_type,
                'games_count' => $games->count()
            ]);
            
        } elseif ($session->isTournament()) {
    // ✅ INICIALIZAR current_stage para Tournament
    $session->current_stage = 1;
    $session->save();
    
    // Para Tournament: generar Stage 1
    $games = $this->gameGenerator->generateStageGames($session);
    
    Log::info('Stage 1 games generated', [
        'session_id' => $session->id,
        'current_stage' => $session->current_stage,
        'games_count' => $games->count()
    ]);
            
        } else {
            // Para Optimized: generar todos los juegos
            $games = $this->gameGenerator->generateAllGames($session);
            
            Log::info('Optimized games generated', [
                'session_id' => $session->id,
                'games_count' => $games->count()
            ]);
        }

        // Verificar que se generaron juegos
        if ($games->isEmpty()) {
            throw new \Exception('No games were generated');
        }

        // ✅ Asignar canchas a los primeros N juegos
        $courts = $session->courts()->where('status', 'available')->orderBy('court_number', 'asc')->get();
        
        Log::info('Assigning courts to initial games', [
            'available_courts' => $courts->count(),
            'games_to_assign' => min($courts->count(), $games->count())
        ]);
        
        foreach ($games->take($courts->count()) as $index => $game) {
            if (isset($courts[$index])) {
                $game->court_id = $courts[$index]->id;
                $game->save();
                
                Log::info('Court assigned to game', [
                    'game_id' => $game->id,
                    'game_number' => $game->game_number,
                    'court_id' => $courts[$index]->id,
                    'court_name' => $courts[$index]->court_name
                ]);
            }
        }

        // ✅ Cambiar status a active y establecer started_at
        $session->status = 'active';
        $session->started_at = now();
        $session->save();

        // ✅ Actualizar progreso inicial
        $session->updateProgress();

        Log::info('Session activated successfully', [
            'session_id' => $session->id,
            'status' => $session->status,
            'started_at' => $session->started_at,
            'total_games' => $games->count()
        ]);

    } catch (\Exception $e) {
        Log::error('Error generating games or activating session', [
            'session_id' => $session->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Rollback: eliminar sesión si falla la generación de juegos
        $session->courts()->delete();
        $session->players()->delete();
        $session->delete();
        
        return response()->json([
            'message' => 'Error generating games: ' . $e->getMessage()
        ], 500);
    }

   return response()->json([
        'message' => 'Session created successfully',
        'session' => $session->load(['courts', 'players', 'games']),
        'session_code' => $session->session_code, // ✅ INCLUIR EN RESPUESTA
    ], 201);
}

// app/Http/Controllers/SessionController.php

/**
 * Buscar sesión por código (público)
 */
public function findByCode(string $code): JsonResponse
{
    $session = Session::where('session_code', strtoupper($code))
        ->where('status', 'active')
        ->first();
    
    if (!$session) {
        return response()->json([
            'message' => 'Session not found or not active'
        ], 404);
    }
    
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
        'elapsed_seconds' => $elapsedSeconds
    ]);
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
 

public function start(Game $game): JsonResponse
{
    if ($game->status !== 'pending') {
        return response()->json([
            'message' => 'This game has already been started or completed'
        ], 422);
    }

    // ✅ BUSCAR LA PRIMERA CANCHA DISPONIBLE (no usar la asignada)
    $session = $game->session;
    $availableCourt = $session->courts()
        ->where('status', 'available')
        ->orderBy('court_number') // ← Tomar siempre la cancha #1 primero
        ->first();

    if (!$availableCourt) {
        return response()->json([
            'message' => 'No courts available. Complete active games first.'
        ], 422);
    }

    // ✅ ASIGNAR LA PRIMERA CANCHA DISPONIBLE (no importa qué cancha tenía antes)
    $game->court_id = $availableCourt->id;
    $game->status = 'active';
    $game->started_at = now();
    $game->save();

    $availableCourt->status = 'occupied';
    $availableCourt->save();

    // ✅ REORGANIZAR INMEDIATAMENTE después de iniciar un juego
    $this->reorganizeGameQueue($session);

    return response()->json([
        'message' => 'Game started',
        'game' => $game->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
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

    // ✅ ELIMINAR juegos pendientes (no solo liberar canchas)
    $pendingCount = $session->games()->where('status', 'pending')->count();
    if ($pendingCount > 0) {
        $deletedCount = $session->games()->where('status', 'pending')->delete();
        
        Log::info('Pending games DELETED when advancing tournament stage', [
            'session_id' => $session->id,
            'from_stage' => $oldStage,
            'to_stage' => $session->current_stage,
            'deleted_count' => $deletedCount
        ]);
    }

    // Generar juegos del nuevo stage
    $games = $this->gameGenerator->generateStageGames($session);

    // Actualizar rankings
    $session->updateRankings();
  
    Log::info('Tournament stage advanced', [
        'session_id' => $session->id,
        'from_stage' => $oldStage,
        'to_stage' => $session->current_stage,
        'games_generated' => $games->count(),
        'previous_pending_deleted' => $pendingCount
    ]);

    return response()->json([
        'message' => "Advanced to Stage {$session->current_stage}",
        'stage' => $session->current_stage,
        'games_generated' => $games->count(),
        'previous_pending_deleted' => $pendingCount,
        'warning' => 'This action is irreversible. All previous stage games have been cleared from the queue.'
    ]);
}

// En SessionController.php - REEMPLAZAR el método finalizeSession

public function finalizeSession(Session $session): JsonResponse
{
    // Verificar autorización
    if ($session->user_id !== auth()->id()) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    // ✅ USAR EL NUEVO MÉTODO: Solo verificar que esté activa
    if (!$session->isReadyToFinalize()) {
        return response()->json([
            'message' => 'Solo sesiones activas pueden ser finalizadas'
        ], 422);
    }

    Log::info('Iniciando finalización manual de sesión', [
        'session_id' => $session->id,
        'session_type' => $session->session_type,
        'pending_games' => $session->games()->where('status', 'pending')->count(),
        'active_games' => $session->games()->where('status', 'active')->count()
    ]);

    // ✅ PASO 1: CANCELAR todos los juegos pendientes y activos
    $pendingGames = $session->games()->whereIn('status', ['pending', 'active'])->get();
    
    foreach ($pendingGames as $game) {
        $game->status = 'cancelled';
        $game->save();
        
        Log::info('Juego cancelado durante finalización', [
            'game_id' => $game->id,
            'game_number' => $game->game_number,
            'previous_status' => $game->getOriginal('status')
        ]);
    }

    // ✅ PASO 2: Actualizar rankings finales
    $session->updateRankings();

    // ✅ PASO 3: Cambiar status a completed
    $session->status = 'completed';
    $session->completed_at = now();
    $session->save();

    // ✅ PASO 4: Preparar datos del podio
    $podiumData = $this->getPodiumData($session);

    Log::info('Sesión finalizada manualmente', [
        'session_id' => $session->id,
        'session_type' => $session->session_type,
        'games_cancelled' => $pendingGames->count(),
        'total_completed_games' => $session->games()->where('status', 'completed')->count(),
        'podium' => $podiumData
    ]);

    return response()->json([
        'message' => 'Session finalized successfully',
        'podium' => $podiumData,
        'session' => $session,
        'games_cancelled' => $pendingGames->count()
    ]);
}
/**
 * Obtener datos del podio según tipo de sesión
 */
private function getPodiumData(Session $session): array
{
    if ($session->isPlayoff8()) {
        return $this->getP8PodiumData($session);
    } elseif ($session->isPlayoff4()) {
        return $this->getP4PodiumData($session);
    } else {
        // Optimized: Top 3 jugadores individuales
        return $this->getOptimizedPodiumData($session);
    }
}

/**
 * Podio para P8 (con Bronze Match)
 */
private function getP8PodiumData(Session $session): array
{
    $goldGame = $session->games()
        ->where('is_playoff_game', true)
        ->where('playoff_round', 'gold')
        ->where('status', 'completed')
        ->first();

    $bronzeGame = $session->games()
        ->where('is_playoff_game', true)
        ->where('playoff_round', 'bronze')
        ->where('status', 'completed')
        ->first();

    if (!$goldGame || !$bronzeGame) {
        return ['type' => 'incomplete'];
    }

    $champions = $goldGame->winner_team === 1
        ? [$goldGame->team1Player1, $goldGame->team1Player2]
        : [$goldGame->team2Player1, $goldGame->team2Player2];

    $runnersUp = $goldGame->winner_team === 1
        ? [$goldGame->team2Player1, $goldGame->team2Player2]
        : [$goldGame->team1Player1, $goldGame->team1Player2];

    $thirdPlace = $bronzeGame->winner_team === 1
        ? [$bronzeGame->team1Player1, $bronzeGame->team1Player2]
        : [$bronzeGame->team2Player1, $bronzeGame->team2Player2];

    return [
        'type' => 'P8',
        'champions' => [
            'players' => array_map(fn($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'rating' => round($p->current_rating, 0)
            ], $champions)
        ],
        'second_place' => [
            'players' => array_map(fn($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'rating' => round($p->current_rating, 0)
            ], $runnersUp)
        ],
        'third_place' => [
            'players' => array_map(fn($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'rating' => round($p->current_rating, 0)
            ], $thirdPlace)
        ]
    ];
}

/**
 * Podio para P4 (sin Bronze Match)
 */
private function getP4PodiumData(Session $session): array
{
    $finalGame = $session->games()
        ->where('is_playoff_game', true)
        ->where('playoff_round', 'final')
        ->where('status', 'completed')
        ->first();

    if (!$finalGame) {
        return ['type' => 'incomplete'];
    }

    $champions = $finalGame->winner_team === 1
        ? [$finalGame->team1Player1, $finalGame->team1Player2]
        : [$finalGame->team2Player1, $finalGame->team2Player2];

    $runnersUp = $finalGame->winner_team === 1
        ? [$finalGame->team2Player1, $finalGame->team2Player2]
        : [$finalGame->team1Player1, $finalGame->team1Player2];

    return [
        'type' => 'P4',
        'champions' => [
            'players' => array_map(fn($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'rating' => round($p->current_rating, 0)
            ], $champions)
        ],
        'second_place' => [
            'players' => array_map(fn($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'rating' => round($p->current_rating, 0)
            ], $runnersUp)
        ]
    ];
}

/**
 * Podio para Optimized (top 3 individuales)
 */
private function getOptimizedPodiumData(Session $session): array
{
    $topPlayers = $session->players()
        ->orderBy('current_rank')
        ->limit(3)
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'display_name' => $p->display_name,
            'rating' => round($p->current_rating, 0),
            'games_played' => $p->games_played,
            'win_percentage' => round($p->win_percentage, 1)
        ]);

    return [
        'type' => 'O',
        'top_players' => $topPlayers->toArray()
    ];
}

 

public function advanceToNextStage(Request $request, Session $session): JsonResponse
{
    try {
        // ✅ VALIDAR: P4/P8 O Tournament
        if (!$session->isPlayoff4() && !$session->isPlayoff8() && !$session->isTournament()) {
            return response()->json([
                'success' => false,
                'error' => 'Solo sesiones P4/P8/Tournament pueden avanzar etapas'
            ], 400);
        }

        $sessionType = $session->session_type;
        
        // ========================================
        // LÓGICA PARA TOURNAMENT (T)
        // ========================================
        if ($session->isTournament()) {
            Log::info('🎯 Iniciando avance a Stage ' . ($session->current_stage + 1), [
                'session_id' => $session->id,
                'current_stage' => $session->current_stage,
            ]);

            // ✅ Verificar que no haya juegos activos del stage actual
            $activeGamesInCurrentStage = $session->games()
                ->where('stage', $session->current_stage)
                ->where('status', 'active')
                ->count();
                
            if ($activeGamesInCurrentStage > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede avanzar: hay juegos activos en el stage actual'
                ], 400);
            }

            // ✅ PASO 1: Estado ANTES
            $beforeState = [
                'total_games' => $session->games()->count(),
                'current_stage' => $session->current_stage,
                'pending_games_current_stage' => $session->games()
                    ->where('stage', $session->current_stage)
                    ->where('status', 'pending')
                    ->count(),
            ];

            Log::info('📊 Estado ANTES de avanzar stage', $beforeState);

            // ✅ PASO 2: CANCELAR juegos pendientes del stage actual
            $cancelledGames = $session->games()
                ->where('stage', $session->current_stage)
                ->where('status', 'pending')
                ->get();
            
            foreach ($cancelledGames as $game) {
                $game->status = 'cancelled';
                $game->court_id = null;
                $game->save();
            }
            
            Log::info('🗑️ Juegos pendientes cancelados', [
                'cancelled_count' => $cancelledGames->count()
            ]);

            // ✅ PASO 3: Actualizar rankings ANTES de avanzar
$session->updateRankings(); // ← CORREGIDO
            
            Log::info('📈 Rankings actualizados después de Stage ' . $session->current_stage, [
                'top_8_players' => $session->players()
                    ->orderBy('current_rank')
                    ->limit(8)
                    ->get()
                    ->mapWithKeys(fn($p) => [$p->current_rank => $p->display_name])
                    ->toArray()
            ]);

            // ✅ PASO 4: Avanzar al siguiente stage
            $session->current_stage++;
            $session->save();
            
            Log::info('⬆️ Stage avanzado', [
                'new_stage' => $session->current_stage
            ]);

            // ✅ PASO 5: Generar juegos del nuevo stage
            $newGames = $this->gameGenerator->generateStageGames($session);
            
            if ($newGames->isEmpty()) {
                Log::warning('❌ No se generaron juegos para Stage ' . $session->current_stage);
                
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudieron generar juegos para el siguiente stage'
                ], 400);
            }

            Log::info('✅ Juegos del nuevo stage generados', [
                'stage' => $session->current_stage,
                'new_games_count' => $newGames->count()
            ]);

            // ✅ PASO 6: Actualizar progreso
            $session->updateProgress();

            // ✅ PASO 7: Estado DESPUÉS
            $afterState = [
                'total_games' => $session->games()->count(),
                'current_stage' => $session->current_stage,
                'pending_games_new_stage' => $session->games()
                    ->where('stage', $session->current_stage)
                    ->where('status', 'pending')
                    ->count(),
            ];

            Log::info('✅ Avance a Stage ' . $session->current_stage . ' completado', [
                'before' => $beforeState,
                'after' => $afterState,
                'new_games' => $newGames->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Advanced to Stage {$session->current_stage} successfully!",
                'new_stage' => $session->current_stage,
                'new_games_count' => $newGames->count(),
                'cancelled_games' => $cancelledGames->count(),
                'total_games' => $session->games()->count()
            ]);
        }

        // ========================================
        // LÓGICA PARA PLAYOFFS (P4/P8)
        // ========================================
        Log::info('🎯 Iniciando avance a playoffs', [
            'session_id' => $session->id,
            'session_type' => $session->session_type,
        ]);

        // ✅ PASO 1: Contar estado actual ANTES de modificar
        $beforeState = [
            'total_games' => $session->games()->count(),
            'pending_games' => $session->games()->where('status', 'pending')->count(),
            'active_games' => $session->games()->where('status', 'active')->count(),
            'completed_games' => $session->games()->where('status', 'completed')->count(),
            'playoff_games' => $session->games()->where('is_playoff_game', true)->count(),
        ];

        Log::info('📊 Estado ANTES de eliminar', $beforeState);

        // ✅ PASO 2: ELIMINAR TODOS los juegos que NO estén completed
        $deletedCount = $session->games()
            ->whereIn('status', ['pending', 'active'])
            ->delete();

        Log::info('🗑️ Juegos eliminados', [
            'deleted_count' => $deletedCount,
            'remaining_games' => $session->games()->count()
        ]);

        // ✅ PASO 3: Actualizar rankings ANTES de generar bracket
        $session->updateRankings();

        Log::info('📈 Rankings actualizados', [
            'top_8_players' => $session->players()
                ->orderBy('current_rank')
                ->limit(8)
                ->get()
                ->mapWithKeys(fn($p) => [$p->current_rank => $p->display_name])
                ->toArray()
        ]);

        // ✅ PASO 4: Generar bracket de playoffs
        $newGames = $this->gameGenerator->generatePlayoffBracket($session);

        if ($newGames->isEmpty()) {
            Log::warning('❌ No se generaron juegos de playoff');
            
            return response()->json([
                'success' => false,
                'error' => 'No se pudieron generar los juegos de playoff'
            ], 400);
        }

        Log::info('✅ Juegos de playoff generados', [
            'new_games_count' => $newGames->count(),
            'playoff_rounds' => $newGames->pluck('playoff_round')->unique()->toArray()
        ]);

        // ✅ PASO 5: ASIGNAR CANCHAS solo a los primeros N juegos
        $courts = $session->courts()->where('status', 'available')->get();
        $gamesWithCourts = 0;
        
        foreach ($newGames->take($courts->count()) as $index => $game) {
            if (isset($courts[$index])) {
                $game->court_id = $courts[$index]->id;
                $game->save();
                $gamesWithCourts++;
            }
        }
        
        // Los juegos restantes quedan SIN cancha
        foreach ($newGames->skip($courts->count()) as $game) {
            $game->court_id = null;
            $game->save();
        }

        Log::info('🏟️ Canchas asignadas', [
            'games_with_courts' => $gamesWithCourts,
            'games_in_queue' => $newGames->count() - $gamesWithCourts
        ]);

        // ✅ PASO 6: Actualizar progreso
        $session->updateProgress();

        // ✅ PASO 7: Estado DESPUÉS
        $afterState = [
            'total_games' => $session->games()->count(),
            'pending_games' => $session->games()->where('status', 'pending')->count(),
            'completed_games' => $session->games()->where('status', 'completed')->count(),
            'playoff_games' => $session->games()->where('is_playoff_game', true)->count(),
        ];

        Log::info('✅ Avance a playoffs completado', [
            'before' => $beforeState,
            'after' => $afterState,
            'new_games' => $newGames->count()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Playoff bracket generated successfully!',
            'new_games_count' => $newGames->count(),
            'games_with_courts' => $gamesWithCourts,
            'games_in_queue' => $newGames->count() - $gamesWithCourts,
            'previous_games_deleted' => $deletedCount,
            'total_games' => $session->games()->count()
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error advancing stage', [
            'session_id' => $session->id,
            'session_type' => $session->session_type,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to advance: ' . $e->getMessage()
        ], 500);
    }
}
 
/**
 * Generar finals de P8 manualmente
 */
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

    // Limpiar juegos pending antes de generar finals
    $pendingCount = $session->games()->where('status', 'pending')->count();
    if ($pendingCount > 0) {
        $deletedCount = $session->games()->where('status', 'pending')->delete();
        
        Log::info('Pending games deleted before generating P8 finals', [
            'session_id' => $session->id,
            'deleted_count' => $deletedCount
        ]);
    }

    // Generar finals
    $games = $this->gameGenerator->generateP8Finals($session, $semifinals);

    Log::info('P8 finals generated successfully', [
        'session_id' => $session->id,
        'games_generated' => $games->count(),
        'previous_pending_deleted' => $pendingCount
    ]);

    // ✅ CORREGIDO: Obtener juegos frescos con relaciones
    $gameIds = $games->pluck('id');
    $gamesWithRelations = Game::whereIn('id', $gameIds)
        ->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
        ->get();

    return response()->json([
        'message' => 'Finals generated successfully',
        'games' => $gamesWithRelations,
        'previous_pending_deleted' => $pendingCount
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