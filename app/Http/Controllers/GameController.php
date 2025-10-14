<?php
namespace App\Http\Controllers;

    use App\Http\Controllers\Controller;
    use App\Models\Game;
    use App\Models\Session;
    use App\Services\GameGeneratorService;     // 👈 Service
    use App\Services\RatingService;
    use Illuminate\Http\Request;
    use Illuminate\Http\JsonResponse;

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

        if (!$game->court_id) {
            return response()->json([
                'message' => 'This game does not have an assigned court'
            ], 422);
        }

        $court = $game->court;
        if ($court->status !== 'available') {
            return response()->json([
                'message' => 'The assigned court is already occupied. Wait for it to become available or complete active games.'
            ], 422);
        }

        $game->status = 'active';
        $game->started_at = now();
        $game->save();

        $court->status = 'occupied';
        $court->save();

        return response()->json([
            'message' => 'Game started',
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

        $availableCourt = $session->courts()
            ->where('status', 'available')
            ->first();

        if (!$availableCourt) {
            $gameToUnassign = $session->games()
                ->where('status', 'pending')
                ->whereNotNull('court_id')
                ->where('id', '!=', $game->id)
                ->orderBy('game_number', 'desc')
                ->first();

            if ($gameToUnassign) {
                $availableCourt = $gameToUnassign->court;
                
                $gameToUnassign->court_id = null;
                $gameToUnassign->save();

                Log::info('Game unassigned to free court', [
                    'unassigned_game' => $gameToUnassign->game_number,
                    'court' => $availableCourt->court_name
                ]);
            } else {
                return response()->json([
                    'message' => 'No courts available. Complete active games first to free a court.'
                ], 422);
            }
        }

        $game->court_id = $availableCourt->id;
        $game->status = 'active';
        $game->started_at = now();
        $game->save();

        $availableCourt->status = 'occupied';
        $availableCourt->save();

        return response()->json([
            'message' => 'Game started on court',
            'game' => $game->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2', 'court'])
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

            // Aplicar nuevas estadísticas
            $this->updatePlayerStats($game);

            $this->ratingService->updateRatings($game);

    // Actualizar rankings
    $this->updateSessionRankings($game->session);
    
    // ✅ AGREGAR ESTA LÍNEA: Actualizar rankings con desempate
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

                $player->updateStats();
            }
        }




        /**
         * Saltar un juego directamente a cancha (Skip to Court)
         */
        /**
         * Saltar un juego directamente a cancha (Skip to Court)
         */ /**
        * Saltar un juego directamente a cancha (Skip to Court)
        */
        


        /**
         * Registrar resultado de un juego
         */

        /**
         * Actualizar estadísticas de jugadores después de un juego
         */
        private function updatePlayerStats(Game $game): void
        {
            $players = [
                $game->team1Player1,
                $game->team1Player2,
                $game->team2Player1,
                $game->team2Player2
            ];

            foreach ($players as $player) {
                $player->games_played++;

                // Si el jugador está en el equipo ganador
                $isWinner = ($game->winner_team === 1 &&
                    ($player->id === $game->team1_player1_id || $player->id === $game->team1_player2_id)) ||
                    ($game->winner_team === 2 &&
                        ($player->id === $game->team2_player1_id || $player->id === $game->team2_player2_id));

                if ($isWinner) {
                    $player->games_won++;
                } else {
                    $player->games_lost++;
                }

                // Actualizar puntos
                if ($player->id === $game->team1_player1_id || $player->id === $game->team1_player2_id) {
                    $player->total_points_won += $game->team1_score;
                    $player->total_points_lost += $game->team2_score;
                } else {
                    $player->total_points_won += $game->team2_score;
                    $player->total_points_lost += $game->team1_score;
                }

                $player->updateStats();
            }
        }

        /**
         * Actualizar rankings de todos los jugadores en la sesión
         */
        private function updateSessionRankings(Session $session): void
        {
            $players = $session->players()
                ->orderBy('current_rating', 'desc')
                ->get();

            foreach ($players as $index => $player) {
                $player->current_rank = $index + 1;
                $player->save();
            }
        }

        /**
         * Mover el siguiente juego pendiente a la cola de activos
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

            $this->updatePlayerStats($game);
            $this->ratingService->updateRatings($game);
            $this->updateSessionRankings($game->session);

                $game->session->updateRankings();

            $game->session->updateProgress();

            // 👇 PRIMERO: Generar finals de P8 si se completaron las semifinals
          //  if ($game->session->isPlayoff8() && $game->is_playoff_game && $game->playoff_round === 'semifinal') {
           //     $this->checkAndGenerateP8Finals($game->session);
           // }

            // 👇 SEGUNDO: Verificar si está completada (ahora SÍ incluye los playoffs que acabamos de generar)
            if ($game->session->isFullyCompleted() && $game->session->status === 'active') {
                $game->session->status = 'completed';
                $game->session->completed_at = now();
                $game->session->save();
            }

            $this->moveNextGameToActive($game->session);

            return response()->json([
                'message' => 'Resultado registrado exitosamente',
                'game' => $game->fresh()->load(['team1Player1', 'team1Player2', 'team2Player1', 'team2Player2']),
                'next_game' => $this->getNextPendingGame($game->session),
                'session_completed' => $game->session->status === 'completed'
            ]);
        }

        private function moveNextGameToActive(Session $session): void
        {
            // Buscar canchas disponibles
            $availableCourts = $session->courts()
                ->where('status', 'available')
                ->get();

            if ($availableCourts->isEmpty()) {
                return;
            }

            // Obtener juegos pendientes del stage actual
            $pendingGames = $session->games()
                ->where('status', 'pending')
                ->where(function ($query) use ($session) {
                    if ($session->isTournament()) {
                        $query->where('stage', $session->current_stage);
                    } else {
                        $query->whereNull('stage');
                    }
                })
                ->orderBy('game_number')
                ->limit($availableCourts->count())
                ->get();

            // Asignar canchas a juegos pendientes
            foreach ($pendingGames as $index => $game) {
                if (isset($availableCourts[$index])) {
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
