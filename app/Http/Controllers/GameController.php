<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Session;
use App\Models\Player; // ← AGREGAR ESTA LÍNEA

use App\Services\GameGeneratorService;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    public function __construct(
        private GameGeneratorService $gameGenerator,
        private RatingService $ratingService
    ) {}

    /**
     * Iniciar un juego (cambiar de pending a active)
     */
public function start(Game $game): JsonResponse
{
    if ($game->status !== 'pending') {
        return response()->json([
            'message' => 'This game has already been started or completed'
        ], 422);
    }

    // ✅ BUSCAR LA PRIMERA CANCHA DISPONIBLE
    $session = $game->session;
    $availableCourt = $session->courts()
        ->where('status', 'available')
        ->orderBy('court_number', 'asc')
        ->first();

    if (!$availableCourt) {
        return response()->json([
            'message' => 'No courts available. Complete active games first.'
        ], 422);
    }

    Log::info('Starting game on first available court', [
        'game_number' => $game->game_number,
        'court_id' => $availableCourt->id,
        'court_name' => $availableCourt->court_name,
        'court_number' => $availableCourt->court_number
    ]);

    // ✅ ASIGNAR LA PRIMERA CANCHA DISPONIBLE
    $game->court_id = $availableCourt->id;
    $game->status = 'active';
    $game->started_at = now();
    $game->save();

    $availableCourt->status = 'occupied';
    $availableCourt->save();

    // ✅ REORGANIZAR INMEDIATAMENTE
    $this->reorganizeGameQueue($session);

    return response()->json([
        'message' => 'Game started on ' . $availableCourt->court_name,
        'game' => $game->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
    ]);
}


public function skipToCourt(Game $game): JsonResponse
{
    if ($game->status !== 'pending') {
        return response()->json([
            'message' => 'Only pending games can skip to court'
        ], 422);
    }

    $session = $game->session;

    // ✅ CORREGIDO: Buscar PRIMERA cancha disponible ordenada
    $availableCourt = $session->courts()
        ->where('status', 'available')
        ->orderBy('court_number', 'asc') // ← AGREGAR 'asc' explícitamente
        ->first();

    if (!$availableCourt) {
        return response()->json([
            'message' => 'No courts available. Complete active games first.'
        ], 422);
    }

    Log::info('Game skipping to first available court', [
        'game_number' => $game->game_number,
        'court_id' => $availableCourt->id,
        'court_name' => $availableCourt->court_name,
        'court_number' => $availableCourt->court_number
    ]);

    $game->court_id = $availableCourt->id;
    $game->status = 'active';
    $game->started_at = now();
    $game->save();

    $availableCourt->status = 'occupied';
    $availableCourt->save();

    $this->reorganizeGameQueue($session);

    return response()->json([
        'message' => 'Game started on ' . $availableCourt->court_name,
        'game' => $game->fresh()->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
    ]);
}


/**
 * Reorganizar la cola de juegos para asegurar que los juegos más prioritarios tengan cancha
 */

private function reorganizeGameQueue(Session $session): void
{
    Log::info('Reorganizing game queue', ['session_id' => $session->id]);
    
    $pendingGames = $session->games()
        ->where('status', 'pending')
        ->orderBy('game_number')
        ->get();

    // ✅ CORREGIDO: Ordenar canchas disponibles por court_number
    $availableCourts = $session->courts()
        ->where('status', 'available')
        ->orderBy('court_number', 'asc') // ← AGREGAR orden explícito
        ->get();

    Log::info('Current queue state', [
        'total_pending' => $pendingGames->count(),
        'available_courts' => $availableCourts->count(),
        'available_courts_list' => $availableCourts->pluck('court_name')
    ]);

    // Limpiar asignaciones
    foreach ($pendingGames as $game) {
        if ($game->court_id) {
            $game->court_id = null;
            $game->save();
        }
    }

    Log::info('Cleared court assignments from pending games');

    // ✅ Asignar canchas en ORDEN: juego #1 → Court 1, juego #2 → Court 2, etc.
    $gamesToAssign = $pendingGames->take($availableCourts->count());

    foreach ($gamesToAssign as $index => $game) {
        if (isset($availableCourts[$index])) {
            $game->court_id = $availableCourts[$index]->id;
            $game->save();
            
            Log::info('Assigned court to game', [
                'game_number' => $game->game_number,
                'court_id' => $availableCourts[$index]->id,
                'court_name' => $availableCourts[$index]->court_name,
                'court_number' => $availableCourts[$index]->court_number
            ]);
        }
    }

    $remainingGames = $pendingGames->skip($availableCourts->count());
    foreach ($remainingGames as $game) {
        $game->court_id = null;
        $game->save();
    }

    Log::info('Queue reorganization completed', [
        'games_assigned_to_courts' => $gamesToAssign->count(),
        'games_in_queue' => $remainingGames->count()
    ]);
}

    public function cancel(Game $game): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json([
                'message' => 'Only active games can be canceled'
            ], 422);
        }

        $game->status = 'pending';
        $game->started_at = null;
        $game->save();

        if ($game->court) {
            $game->court->status = 'available';
            $game->court->save();
        }

        return response()->json([
            'message' => 'Game canceled'
        ]);
    }

    /**
     * Actualizar resultado de un juego ya completado
     */
    public function updateScore(Request $request, Game $game): JsonResponse
    {
        // Validar que el juego esté completado
        if ($game->status !== 'completed') {
            return response()->json([
                'message' => 'Solo se pueden editar juegos completados'
            ], 422);
        }

        $validated = $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
        ]);

        $session = $game->session;

        // Validar que no haya empate
        if ($validated['team1_score'] === $validated['team2_score']) {
            return response()->json([
                'message' => 'No puede haber empate'
            ], 422);
        }

        $winnerScore = max($validated['team1_score'], $validated['team2_score']);
        $loserScore = min($validated['team1_score'], $validated['team2_score']);

        // Validar puntos mínimos
        if ($winnerScore < $session->points_per_game) {
            return response()->json([
                'message' => "El ganador debe tener al menos {$session->points_per_game} puntos"
            ], 422);
        }

        // Validar margen de victoria
        if (($winnerScore - $loserScore) < $session->win_by) {
            return response()->json([
                'message' => "Debe ganar por al menos {$session->win_by} punto(s)"
            ], 422);
        }

        // Guardar scores antiguos para revertir estadísticas
        $oldTeam1Score = $game->team1_score;
        $oldTeam2Score = $game->team2_score;
        $oldWinnerTeam = $game->winner_team;

        // Actualizar scores
        $game->team1_score = $validated['team1_score'];
        $game->team2_score = $validated['team2_score'];
        $game->winner_team = $validated['team1_score'] > $validated['team2_score'] ? 1 : 2;
        $game->save();

        // Revertir estadísticas del juego anterior
        $this->revertPlayerStats($game, $oldTeam1Score, $oldTeam2Score, $oldWinnerTeam);

        // ✅ SOLO usar RatingService ELO para ratings (no updatePlayerStats)
        $this->ratingService->updateRatings($game);

        // ✅ Actualizar rankings simplificados
        $game->session->updateRankings();

        return response()->json([
            'message' => 'Puntaje actualizado exitosamente',
            'game' => $game->fresh()->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2'])
        ]);
    }

    /**
     * Revertir estadísticas de jugadores antes de actualizar
     */
    private function revertPlayerStats(Game $game, int $oldTeam1Score, int $oldTeam2Score, int $oldWinnerTeam): void
    {
        $players = [
            $game->team1Player1,
            $game->team1Player2,
            $game->team2Player1,
            $game->team2Player2
        ];

        foreach ($players as $player) {
            // Revertir juego jugado
            $player->games_played--;

            // Revertir victoria/derrota
            $wasWinner = ($oldWinnerTeam === 1 &&
                ($player->id === $game->team1_player1_id || $player->id === $game->team1_player2_id)) ||
                ($oldWinnerTeam === 2 &&
                    ($player->id === $game->team2_player1_id || $player->id === $game->team2_player2_id));

            if ($wasWinner) {
                $player->games_won--;
            } else {
                $player->games_lost--;
            }

            // Revertir puntos
            if ($player->id === $game->team1_player1_id || $player->id === $game->team1_player2_id) {
                $player->total_points_won -= $oldTeam1Score;
                $player->total_points_lost -= $oldTeam2Score;
            } else {
                $player->total_points_won -= $oldTeam2Score;
                $player->total_points_lost -= $oldTeam1Score;
            }

            // ✅ Solo actualizar porcentajes básicos, no ratings
            $this->updateBasicPlayerStats($player);
        }
    }

    /**
     * Registrar resultado de un juego
     */
    public function submitScore(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'team1_sets_won' => 'nullable|integer|min:0',
            'team2_sets_won' => 'nullable|integer|min:0'
        ]);

        if ($game->status === 'completed') {
            return response()->json([
                'message' => 'Este juego ya fue completado'
            ], 422);
        }

        $game->team1_score = $validated['team1_score'];
        $game->team2_score = $validated['team2_score'];

        if (isset($validated['team1_sets_won'])) {
            $game->team1_sets_won = $validated['team1_sets_won'];
        }
        if (isset($validated['team2_sets_won'])) {
            $game->team2_sets_won = $validated['team2_sets_won'];
        }

        if (!$game->isScoreValid()) {
            return response()->json([
                'message' => 'Score inválido. Verifica las reglas del juego.',
                'errors' => [
                    'score' => 'El score no cumple con las reglas configuradas'
                ]
            ], 422);
        }

        $game->winner_team = $game->team1_score > $game->team2_score ? 1 : 2;
        $game->status = 'completed';
        $game->completed_at = now();
        $game->save();

        if ($game->court) {
            $game->court->status = 'available';
            $game->court->save();
        }

        // ✅ SOLO usar RatingService ELO para ratings
        $this->ratingService->updateRatings($game);
        
        // ✅ Actualizar stats básicos SIN ratings
        $this->updateBasicPlayerStatsFromGame($game);

        // ✅ Actualizar rankings simplificados
        $game->session->updateRankings();

        $game->session->updateProgress();

             $this->reorganizeGameQueue($game->session);


        return response()->json([
            'message' => 'Resultado registrado exitosamente',
            'game' => $game->fresh()->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2']),
            'next_game' => $this->getNextPendingGame($game->session),
            'session_completed' => false
        ]);
    }

/**
 * Obtener el juego que debe tener el botón "Start Game" activo
 */
public function getPrimaryActiveGame(Session $session): JsonResponse
{
    $primaryGame = $session->games()
        ->where('status', 'pending')
        ->whereNotNull('court_id')
        ->orderBy('game_number')
        ->first();

    $result = [
        'primary_active_game' => $primaryGame ? $primaryGame->load([
            'team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'
        ]) : null
    ];

    Log::debug('Primary active game check', [
        'session_id' => $session->id,
        'primary_game_number' => $primaryGame ? $primaryGame->game_number : null,
        'primary_game_court' => $primaryGame && $primaryGame->court ? $primaryGame->court->court_name : null
    ]);

    return response()->json($result);
}

    /**
     * Actualizar stats básicos SIN ratings (solo porcentajes)
     */
    private function updateBasicPlayerStatsFromGame(Game $game): void
    {
        $players = [
            $game->team1Player1, $game->team1Player2,
            $game->team2Player1, $game->team2Player2
        ];

        foreach ($players as $player) {
            $player->games_played++;
            
            $isWinner = ($game->winner_team === 1 &&
                in_array($player->id, [$game->team1_player1_id, $game->team1_player2_id])) ||
                ($game->winner_team === 2 &&
                    in_array($player->id, [$game->team2_player1_id, $game->team2_player2_id]));

            if ($isWinner) {
                $player->games_won++;
            } else {
                $player->games_lost++;
            }

            // Actualizar puntos
            if (in_array($player->id, [$game->team1_player1_id, $game->team1_player2_id])) {
                $player->total_points_won += $game->team1_score;
                $player->total_points_lost += $game->team2_score;
            } else {
                $player->total_points_won += $game->team2_score;
                $player->total_points_lost += $game->team1_score;
            }

            // ✅ Solo actualizar porcentajes, NO ratings
            $this->updateBasicPlayerStats($player);
        }
    }

   private function updateBasicPlayerStats(Player $player): void // ← Ahora usa App\Models\Player
    {
        $player->win_percentage = $player->games_played > 0 
            ? ($player->games_won / $player->games_played) * 100 
            : 0;
            
        $totalPoints = $player->total_points_won + $player->total_points_lost;
        $player->points_won_percentage = $totalPoints > 0 
            ? ($player->total_points_won / $totalPoints) * 100 
            : 0;

        $player->save();
    }

  private function moveNextGameToActive(Session $session): void
{
    // Primero reorganizar la cola
    $this->reorganizeGameQueue($session);
    
    // Luego buscar canchas disponibles (por si acaso quedaron después de la reorganización)
    $availableCourts = $session->courts()
        ->where('status', 'available')
        ->get();

    if ($availableCourts->isEmpty()) {
        return;
    }

    // Obtener juegos pendientes del stage actual, ordenados por prioridad
    $pendingGames = $session->games()
        ->where('status', 'pending')
        ->where(function ($query) use ($session) {
            if ($session->isTournament()) {
                $query->where('stage', $session->current_stage);
            } else {
                $query->whereNull('stage');
            }
        })
        ->orderBy('game_number') // ← Siempre ordenar por game_number para mantener prioridad
        ->limit($availableCourts->count())
        ->get();

    // Asignar canchas SOLO a juegos que no tengan cancha
    foreach ($pendingGames as $index => $game) {
        if (!$game->court_id && isset($availableCourts[$index])) {
            $game->court_id = $availableCourts[$index]->id;
            $game->save();
        }
    }
}

    /**
     * Obtener el siguiente juego pendiente
     */
    private function getNextPendingGame(Session $session): ?Game
    {
        return $session->games()
            ->where('status', 'pending')
            ->where(function ($query) use ($session) {
                if ($session->isTournament()) {
                    $query->where('stage', $session->current_stage);
                }
            })
            ->with(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2'])
            ->first();
    }

    private function checkAndGenerateP8Finals(Session $session): void
    {
        $semifinals = $session->games()
            ->where('is_playoff_game', true)
            ->where('playoff_round', 'semifinal')
            ->get();

        if ($semifinals->count() !== 2 || $semifinals->where('status', 'completed')->count() !== 2) {
            return;
        }

        $existingFinals = $session->games()
            ->where('is_playoff_game', true)
            ->whereIn('playoff_round', ['gold', 'bronze'])
            ->exists();

        if ($existingFinals) {
            return;
        }

        $this->gameGenerator->generateP8Finals($session, $semifinals);
    }

    /**
     * ❌ ELIMINADO: updatePlayerStats() - Ya no se usa
     * ❌ ELIMINADO: updateSessionRankings() - Ya no se usa (ahora está en Session model)
     */
}