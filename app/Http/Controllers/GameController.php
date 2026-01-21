<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Session;
use App\Models\Player; // ← AGREGAR ESTA LÍNEA
use App\Jobs\UpdateGameStatsJob; // ← AGREGAR ESTA LÍNEA
use Illuminate\Support\Facades\DB; // ← AGREGAR ESTA LÍNEA
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
public function submitScore(Request $request, $gameId)
{
    // ✅ PASO 0: Iniciar transacción
    DB::beginTransaction();

    try {
        // ═══════════════════════════════════════════════════════
        // ✅ PASO 1: CARGAR JUEGO CON SESIÓN (Eager Loading)
        // ═══════════════════════════════════════════════════════
        $game = Game::with('session')->findOrFail($gameId);

        Log::info('📝 Starting score submission', [
            'game_id' => $gameId,
            'session_id' => $game->session_id
        ]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 2: VALIDAR INPUT
        // ═══════════════════════════════════════════════════════
        $numberOfSets = $game->session->number_of_sets ?? '1';
        $isBestOf3 = $numberOfSets === '3';

        if ($isBestOf3) {
            // Validación para Best of 3
            $validated = $request->validate([
                'team1_set1_score' => 'required|integer|min:0',
                'team2_set1_score' => 'required|integer|min:0',
                'team1_set2_score' => 'required|integer|min:0',
                'team2_set2_score' => 'required|integer|min:0',
                'team1_set3_score' => 'nullable|integer|min:0',
                'team2_set3_score' => 'nullable|integer|min:0',
            ]);
        } else {
            // Validación para Best of 1
            $validated = $request->validate([
                'team1_score' => 'required|integer|min:0',
                'team2_score' => 'required|integer|min:0',
            ]);
        }

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 3: CALCULAR GANADOR Y ACTUALIZAR JUEGO
        // ═══════════════════════════════════════════════════════
        if ($isBestOf3) {
            // Calcular sets ganados
            $team1SetsWon = 0;
            $team2SetsWon = 0;

            // Set 1
            if ($validated['team1_set1_score'] > $validated['team2_set1_score']) {
                $team1SetsWon++;
            } else {
                $team2SetsWon++;
            }

            // Set 2
            if ($validated['team1_set2_score'] > $validated['team2_set2_score']) {
                $team1SetsWon++;
            } else {
                $team2SetsWon++;
            }

            // Set 3 (si se jugó)
            if (isset($validated['team1_set3_score']) && isset($validated['team2_set3_score'])) {
                if ($validated['team1_set3_score'] > $validated['team2_set3_score']) {
                    $team1SetsWon++;
                } else {
                    $team2SetsWon++;
                }
            }

            // Calcular scores totales
            $team1TotalScore = $validated['team1_set1_score'] +
                               $validated['team1_set2_score'] +
                               ($validated['team1_set3_score'] ?? 0);

            $team2TotalScore = $validated['team2_set1_score'] +
                               $validated['team2_set2_score'] +
                               ($validated['team2_set3_score'] ?? 0);

            // Determinar ganador
            $winnerTeam = $team1SetsWon > $team2SetsWon ? 1 : 2;

            // Actualizar juego
            $game->update([
                'team1_set1_score' => $validated['team1_set1_score'],
                'team2_set1_score' => $validated['team2_set1_score'],
                'team1_set2_score' => $validated['team1_set2_score'],
                'team2_set2_score' => $validated['team2_set2_score'],
                'team1_set3_score' => $validated['team1_set3_score'] ?? null,
                'team2_set3_score' => $validated['team2_set3_score'] ?? null,
                'team1_sets_won' => $team1SetsWon,
                'team2_sets_won' => $team2SetsWon,
                'team1_score' => $team1TotalScore,
                'team2_score' => $team2TotalScore,
                'winner_team' => $winnerTeam,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        } else {
            // Best of 1
            $winnerTeam = $validated['team1_score'] > $validated['team2_score'] ? 1 : 2;

            $game->update([
                'team1_score' => $validated['team1_score'],
                'team2_score' => $validated['team2_score'],
                'winner_team' => $winnerTeam,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        Log::info('✅ Score updated', [
            'game_id' => $gameId,
            'winner_team' => $winnerTeam
        ]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 4: LIBERAR CANCHA
        // ═══════════════════════════════════════════════════════
        if ($game->court) {
            $game->court->status = 'available';
            $game->court->save();
        }

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 5: ACTUALIZAR RATINGS (RatingService)
        // ═══════════════════════════════════════════════════════
        // Recargar con jugadores
        $game->load([
            'team1Player1',
            'team1Player2',
            'team2Player1',
            'team2Player2'
        ]);

        // Actualizar ratings
        $this->ratingService->updateRatings($game);

        Log::info('✅ Ratings updated', ['game_id' => $gameId]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 6: ACTUALIZAR ESTADÍSTICAS DE JUGADORES
        // ═══════════════════════════════════════════════════════
        $this->updateBasicPlayerStatsFromGame($game);

        Log::info('✅ Player stats updated', ['game_id' => $gameId]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 7: ACTUALIZAR RANKINGS (Session Model)
        // ═══════════════════════════════════════════════════════
        $game->session->updateRankings();

        Log::info('✅ Rankings updated', ['session_id' => $game->session_id]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 8: ACTUALIZAR PROGRESO (Session Model)
        // ═══════════════════════════════════════════════════════
        $game->session->updateProgress();

        Log::info('✅ Progress updated', ['session_id' => $game->session_id]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 9: REORGANIZAR COLA DE JUEGOS
        // ═══════════════════════════════════════════════════════
        $this->reorganizeGameQueue($game->session);

        Log::info('✅ Game queue reorganized', ['session_id' => $game->session_id]);

        // ═══════════════════════════════════════════════════════
        // ✅ PASO 10: COMMIT Y RETORNAR RESPUESTA
        // ═══════════════════════════════════════════════════════
        DB::commit();

        // Recargar juego con todas las relaciones
        $game->load([
            'team1Player1:id,first_name,last_initial,current_rating',
            'team1Player2:id,first_name,last_initial,current_rating',
            'team2Player1:id,first_name,last_initial,current_rating',
            'team2Player2:id,first_name,last_initial,current_rating',
            'court:id,court_name,status'
        ]);

        Log::info('✅ Score submission completed successfully', [
            'game_id' => $gameId,
            'winner_team' => $winnerTeam
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Score recorded successfully',
            'game' => $game
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollback();

        Log::error('❌ Validation error in submitScore', [
            'game_id' => $gameId,
            'errors' => $e->errors()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::rollback();

        Log::error('❌ Error in submitScore', [
            'game_id' => $gameId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error recording score: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * ✅ MÉTODO AUXILIAR: Actualizar estadísticas básicas de jugadores
     */
private function updateBasicPlayerStatsFromGame(Game $game)
{
    $players = [
        $game->team1Player1,
        $game->team1Player2,
        $game->team2Player1,
        $game->team2Player2
    ];

    foreach ($players as $player) {
        if (!$player) continue;

        // Calcular estadísticas
        $gamesPlayed = Game::where(function($query) use ($player) {
            $query->where('team1_player1_id', $player->id)
                  ->orWhere('team1_player2_id', $player->id)
                  ->orWhere('team2_player1_id', $player->id)
                  ->orWhere('team2_player2_id', $player->id);
        })
        ->where('session_id', $player->session_id)
        ->where('status', 'completed')
        ->count();

        $gamesWon = Game::where('status', 'completed')
            ->where('session_id', $player->session_id)
            ->where(function($query) use ($player) {
                $query->where(function($q) use ($player) {
                    // Equipo 1 ganó
                    $q->where('winner_team', 1)
                      ->where(function($q2) use ($player) {
                          $q2->where('team1_player1_id', $player->id)
                             ->orWhere('team1_player2_id', $player->id);
                      });
                })->orWhere(function($q) use ($player) {
                    // Equipo 2 ganó
                    $q->where('winner_team', 2)
                      ->where(function($q2) use ($player) {
                          $q2->where('team2_player1_id', $player->id)
                             ->orWhere('team2_player2_id', $player->id);
                      });
                });
            })
            ->count();

        $gamesLost = $gamesPlayed - $gamesWon;

        // Calcular puntos ganados/perdidos
        $playerGames = Game::where('status', 'completed')
            ->where('session_id', $player->session_id)
            ->where(function($query) use ($player) {
                $query->where('team1_player1_id', $player->id)
                      ->orWhere('team1_player2_id', $player->id)
                      ->orWhere('team2_player1_id', $player->id)
                      ->orWhere('team2_player2_id', $player->id);
            })
            ->get();

        $totalPointsWon = 0;
        $totalPointsLost = 0;

        foreach ($playerGames as $g) {
            $isTeam1 = ($g->team1_player1_id == $player->id ||
                       $g->team1_player2_id == $player->id);

            if ($isTeam1) {
                $totalPointsWon += $g->team1_score ?? 0;
                $totalPointsLost += $g->team2_score ?? 0;
            } else {
                $totalPointsWon += $g->team2_score ?? 0;
                $totalPointsLost += $g->team1_score ?? 0;
            }
        }

        // Calcular porcentajes
        $winPercentage = $gamesPlayed > 0
            ? round(($gamesWon / $gamesPlayed) * 100, 2)
            : 0;

        $totalPoints = $totalPointsWon + $totalPointsLost;
        $pointsWonPercentage = $totalPoints > 0
            ? round(($totalPointsWon / $totalPoints) * 100, 2)
            : 0;

        // Actualizar jugador
        $player->update([
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'games_lost' => $gamesLost,
            'total_points_won' => $totalPointsWon,
            'total_points_lost' => $totalPointsLost,
            'win_percentage' => $winPercentage,
            'points_won_percentage' => $pointsWonPercentage,
        ]);
    }
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
     * ✅ ACTUALIZADO: Actualizar score con recalculación completa de ratings
     */
    public function updateScore(Request $request, Game $game): JsonResponse
    {
        DB::beginTransaction();

        try {
            // ═══════════════════════════════════════════════════════
            // ✅ PASO 1: VALIDAR
            // ═══════════════════════════════════════════════════════
            if ($game->status !== 'completed') {
                return response()->json([
                    'message' => 'Solo se pueden editar juegos completados'
                ], 422);
            }

            $session = $game->session;

            // Validación condicional
            if ($session->number_of_sets === '3') {
                $validated = $request->validate([
                    'team1_score' => 'required|integer|min:0',
                    'team2_score' => 'required|integer|min:0',
                    'team1_set1_score' => 'required|integer|min:0',
                    'team2_set1_score' => 'required|integer|min:0',
                    'team1_set2_score' => 'required|integer|min:0',
                    'team2_set2_score' => 'required|integer|min:0',
                    'team1_set3_score' => 'nullable|integer|min:0',
                    'team2_set3_score' => 'nullable|integer|min:0',
                    'team1_sets_won' => 'required|integer|min:0|max:3',
                    'team2_sets_won' => 'required|integer|min:0|max:3'
                ]);
            } else {
                $validated = $request->validate([
                    'team1_score' => 'required|integer|min:0',
                    'team2_score' => 'required|integer|min:0',
                ]);
            }

            Log::info('🔄 Iniciando recalculación completa de ratings', [
                'game_id' => $game->id,
                'game_number' => $game->game_number,
                'session_id' => $session->id,
                'old_winner' => $game->winner_team
            ]);

            // ═══════════════════════════════════════════════════════
            // ✅ PASO 2: ACTUALIZAR SCORE DEL JUEGO EDITADO
            // ═══════════════════════════════════════════════════════
            $game->team1_score = $validated['team1_score'];
            $game->team2_score = $validated['team2_score'];

            if ($session->number_of_sets === '3') {
                $game->team1_set1_score = $validated['team1_set1_score'];
                $game->team2_set1_score = $validated['team2_set1_score'];
                $game->team1_set2_score = $validated['team1_set2_score'];
                $game->team2_set2_score = $validated['team2_set2_score'];
                $game->team1_set3_score = $validated['team1_set3_score'] ?? null;
                $game->team2_set3_score = $validated['team2_set3_score'] ?? null;
                $game->team1_sets_won = $validated['team1_sets_won'];
                $game->team2_sets_won = $validated['team2_sets_won'];
            }

            $game->winner_team = $validated['team1_score'] > $validated['team2_score'] ? 1 : 2;
            $game->save();

            Log::info('✅ Score actualizado', [
                'game_id' => $game->id,
                'new_winner_team' => $game->winner_team
            ]);

            // ═══════════════════════════════════════════════════════
            // ✅ PASO 3: RECALCULAR TODOS LOS RATINGS DESDE CERO
            // ═══════════════════════════════════════════════════════
            $this->ratingService->recalculateAllRatings($session->id);

            Log::info('✅ Todos los ratings recalculados desde cero');

            // ═══════════════════════════════════════════════════════
            // ✅ PASO 4: RECALCULAR ESTADÍSTICAS DE JUGADORES
            // ═══════════════════════════════════════════════════════
            $players = Player::where('session_id', $session->id)->get();

            foreach ($players as $player) {
                $this->recalculatePlayerStatsFromScratch($player, $session);
            }

            Log::info('✅ Estadísticas de jugadores recalculadas');

            // ═══════════════════════════════════════════════════════
            // ✅ PASO 5: ACTUALIZAR RANKINGS
            // ═══════════════════════════════════════════════════════
            $session->updateRankings();

            Log::info('✅ Rankings actualizados');

            DB::commit();

            // ═══════════════════════════════════════════════════════
            // ✅ PASO 6: RETORNAR RESPUESTA
            // ═══════════════════════════════════════════════════════
            $completedGamesCount = Game::where('session_id', $session->id)
                ->where('status', 'completed')
                ->count();

            Log::info('✅ Recalculación completa exitosa', [
                'game_id' => $game->id,
                'games_recalculated' => $completedGamesCount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Score updated and all ratings recalculated successfully',
                'game' => $game->fresh()->load([
                    'team1Player1:id,first_name,last_initial,current_rating',
                    'team1Player2:id,first_name,last_initial,current_rating',
                    'team2Player1:id,first_name,last_initial,current_rating',
                    'team2Player2:id,first_name,last_initial,current_rating'
                ]),
                'games_recalculated' => $completedGamesCount,
                'warning' => 'All player ratings have been recalculated from scratch'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('❌ Error en recalculación de ratings', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error recalculating ratings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Recalcular estadísticas de un jugador desde cero
     */
    private function recalculatePlayerStatsFromScratch(Player $player, Session $session): void
    {
        // Contar juegos jugados
        $gamesPlayed = Game::where(function($query) use ($player) {
            $query->where('team1_player1_id', $player->id)
                ->orWhere('team1_player2_id', $player->id)
                ->orWhere('team2_player1_id', $player->id)
                ->orWhere('team2_player2_id', $player->id);
        })
            ->where('session_id', $session->id)
            ->where('status', 'completed')
            ->count();

        // Contar juegos ganados
        $gamesWon = Game::where('status', 'completed')
            ->where('session_id', $session->id)
            ->where(function($query) use ($player) {
                $query->where(function($q) use ($player) {
                    // Equipo 1 ganó
                    $q->where('winner_team', 1)
                        ->where(function($q2) use ($player) {
                            $q2->where('team1_player1_id', $player->id)
                                ->orWhere('team1_player2_id', $player->id);
                        });
                })->orWhere(function($q) use ($player) {
                    // Equipo 2 ganó
                    $q->where('winner_team', 2)
                        ->where(function($q2) use ($player) {
                            $q2->where('team2_player1_id', $player->id)
                                ->orWhere('team2_player2_id', $player->id);
                        });
                });
            })
            ->count();

        $gamesLost = $gamesPlayed - $gamesWon;

        // Calcular puntos ganados/perdidos
        $playerGames = Game::where('status', 'completed')
            ->where('session_id', $session->id)
            ->where(function($query) use ($player) {
                $query->where('team1_player1_id', $player->id)
                    ->orWhere('team1_player2_id', $player->id)
                    ->orWhere('team2_player1_id', $player->id)
                    ->orWhere('team2_player2_id', $player->id);
            })
            ->get();

        $totalPointsWon = 0;
        $totalPointsLost = 0;

        foreach ($playerGames as $g) {
            $isTeam1 = ($g->team1_player1_id == $player->id ||
                $g->team1_player2_id == $player->id);

            if ($isTeam1) {
                $totalPointsWon += $g->team1_score ?? 0;
                $totalPointsLost += $g->team2_score ?? 0;
            } else {
                $totalPointsWon += $g->team2_score ?? 0;
                $totalPointsLost += $g->team1_score ?? 0;
            }
        }

        // Calcular porcentajes
        $winPercentage = $gamesPlayed > 0
            ? round(($gamesWon / $gamesPlayed) * 100, 2)
            : 0;

        $totalPoints = $totalPointsWon + $totalPointsLost;
        $pointsWonPercentage = $totalPoints > 0
            ? round(($totalPointsWon / $totalPoints) * 100, 2)
            : 0;

        // Actualizar jugador
        $player->update([
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'games_lost' => $gamesLost,
            'total_points_won' => $totalPointsWon,
            'total_points_lost' => $totalPointsLost,
            'win_percentage' => $winPercentage,
            'points_won_percentage' => $pointsWonPercentage,
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

    private function reorganizeGameQueue($session)
{
    // ✅ EARLY RETURN: Si no hay juegos pendientes, salir
    $pendingCount = $session->games()->where('status', 'pending')->count();

    if ($pendingCount === 0) {
        Log::debug('No pending games to reorganize', ['session_id' => $session->id]);
        return;
    }

    Log::info('🔄 Reorganizing game queue', [
        'session_id' => $session->id,
        'pending_count' => $pendingCount
    ]);

    // Obtener juegos pendientes SIN cancha asignada
    $gamesWithoutCourt = $session->games()
        ->where('status', 'pending')
        ->whereNull('court_id') // ← Solo juegos sin cancha
        ->orderBy('game_number')
        ->get();

    // Obtener canchas disponibles
    $availableCourts = $session->courts()
        ->where('status', 'available')
        ->orderBy('court_number', 'asc')
        ->get();

    // ✅ EARLY RETURN: Si no hay canchas disponibles, salir
    if ($availableCourts->isEmpty()) {
        Log::debug('No available courts', ['session_id' => $session->id]);
        return;
    }

    Log::debug('🔍 Games without court assignment', [
        'count' => $gamesWithoutCourt->count(),
        'available_courts' => $availableCourts->count()
    ]);

    // ✅ ASIGNAR canchas SOLO a juegos que NO tienen cancha
    $gamesToAssign = $gamesWithoutCourt->take($availableCourts->count());

    foreach ($gamesToAssign as $index => $game) {
        if (isset($availableCourts[$index])) {
            $game->court_id = $availableCourts[$index]->id;
            $game->save();

            Log::debug('✅ Assigned court to game', [
                'game_number' => $game->game_number,
                'court_id' => $availableCourts[$index]->id,
                'court_name' => $availableCourts[$index]->court_name
            ]);
        }
    }

    Log::info('✅ Queue reorganized', [
        'session_id' => $session->id,
        'games_assigned' => $gamesToAssign->count()
    ]);
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
       }
