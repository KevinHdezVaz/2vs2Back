<?php

namespace App\Services;

use Log;
use App\Models\Game;
use App\Models\Player;
use App\Models\Session;
use Illuminate\Support\Collection;

class GameGeneratorService
{
    public function generateInitialGames(Session $session): Collection
    {
        $template = $this->loadTemplate($session);
        
        if ($template) {
            Log::info('Usando template JSON para generar juegos', [
                'template' => $this->getTemplateFilename($session)
            ]);
            return $this->generateFromTemplate($session, $template);
        }
        
        Log::warning('Template JSON no encontrado, usando lógica por defecto', [
            'expected_file' => $this->getTemplateFilename($session)
        ]);
        
        return $this->generateRandomGames($session);
    }



    /**
 * Validar que la configuración de sesión tiene un template disponible
 */
public function validateSessionConfiguration(Session $session): array
{
    // Validación 1: Mínimo 4 jugadores por cancha
    $minPlayersRequired = $session->number_of_courts * 4;
    if ($session->number_of_players < $minPlayersRequired) {
        return [
            'valid' => false,
            'message' => "You need at least {$minPlayersRequired} players for {$session->number_of_courts} court(s). Each court requires 4 players minimum."
        ];
    }

    // Validación 2: Verificar que existe el template JSON
    $template = $this->loadTemplate($session);
    if (!$template) {
        return [
            'valid' => false,
            'message' => "That session configuration has not been created yet. Please try a different combination of players, courts & hours - we will add more options soon!"
        ];
    }

    return [
        'valid' => true,
        'message' => 'Configuration is valid'
    ];
}



    private function loadTemplate(Session $session): ?array
    {
        $filename = $this->getTemplateFilename($session);
        $path = storage_path("app/game_templates/{$filename}");
        
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
        
        return null;
    }

    private function getTemplateFilename(Session $session): string
    {
        return sprintf(
            '%dC%dH%dP-%s.json',
            $session->number_of_courts,
            $session->duration_hours,
            $session->number_of_players,
            $session->session_type
        );
    }

 

    private function generateFromTemplate(Session $session, array $template): Collection
{
    $players = $session->players->keyBy('id')->values();
    $games = collect();
    $gameNumber = 1;

    // ✅ NUEVO: Para P4/P8, generar SOLO el primer bloque inicialmente
    foreach ($template['blocks'] as $blockIndex => $block) {
        // ✅ SOLO generar el PRIMER bloque inicialmente para P4/P8
        if ($session->isPlayoff4() || $session->isPlayoff8()) {
            if ($blockIndex > 0) {
                Log::info('Reservando bloque para avanzar manualmente', [
                    'label' => $block['label'],
                    'block_index' => $blockIndex,
                    'session_type' => $session->session_type
                ]);
                continue;
            }
        }
        
        // Para torneos, mantener lógica existente
        if ($session->isTournament()) {
            $blockStage = $this->getStageFromBlock($block['label']);
            if ($blockStage !== 1) {
                Log::info('Saltando stage posterior en generación inicial', [
                    'label' => $block['label'],
                    'stage' => $blockStage
                ]);
                continue;
            }
        }

        foreach ($block['rounds'] as $round) {
            foreach ($round['courts'] as $courtData) {
                $teamA = $courtData['A'];
                $teamB = $courtData['B'];

                $team1Player1 = $this->getPlayerFromNotation($teamA[0], $players);
                $team1Player2 = $this->getPlayerFromNotation($teamA[1], $players);
                $team2Player1 = $this->getPlayerFromNotation($teamB[0], $players);
                $team2Player2 = $this->getPlayerFromNotation($teamB[1], $players);

                if (!$team1Player1 || !$team1Player2 || !$team2Player1 || !$team2Player2) {
                    continue;
                }

                $stage = null;
                if ($session->isTournament()) {
                    $stage = $this->getStageFromBlock($block['label']);
                }

                $game = Game::create([
                    'session_id' => $session->id,
                    'game_number' => $gameNumber,
                    'stage' => $stage,
                    'status' => 'pending',
                    'team1_player1_id' => $team1Player1->id,
                    'team1_player2_id' => $team1Player2->id,
                    'team2_player1_id' => $team2Player1->id,
                    'team2_player2_id' => $team2Player2->id,
                ]);

                $games->push($game);
                $gameNumber++;
            }
        }
    }

    $this->assignCourtsToGames($session, $games);
    return $games;
}

// ← AGREGAR ESTE MÉTODO
private function isPlayoffBlock(string $label): bool
{
       $playoffKeywords = ['Final', 'Semifinals', 'Medals', 'Playoff Finals', 'Bronze', 'Qualifier'];
    foreach ($playoffKeywords as $keyword) {
        if (stripos($label, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

    private function getPlayerFromNotation(string $notation, Collection $players): ?Player
    {
        if (str_contains($notation, 'Winner') || str_contains($notation, 'Loser')) {
            return null;
        }

        preg_match('/P(\d+)/', $notation, $matches);
        
        if (isset($matches[1])) {
            $playerIndex = (int)$matches[1] - 1;
            return $players->get($playerIndex);
        }

        return null;
    }

    private function getStageFromBlock(string $label): ?int
    {
        if (str_contains($label, 'Stage 1')) return 1;
        if (str_contains($label, 'Stage 2')) return 2;
        if (str_contains($label, 'Stage 3')) return 3;
        return null;
    }


    public function generateAllGames(Session $session): Collection
{
    return $this->generateInitialGames($session);
}

private function assignCourtsToGames(Session $session, Collection $games): void
{
    $courts = $session->courts()
        ->where('status', 'available')  // ✅ ASEGURAR que sean available
        ->get();
    
    Log::info('Assigning courts to games', [
        'session_id' => $session->id,
        'total_games' => $games->count(),
        'available_courts' => $courts->count(),
        'courts_data' => $courts->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->court_name,
            'status' => $c->status
        ])->toArray()
    ]);
    
    // ✅ SOLO asignar canchas a los primeros N juegos (donde N = número de canchas)
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
    
    // ✅ Los juegos restantes quedan SIN cancha (en cola)
    foreach ($games->skip($courts->count()) as $game) {
        $game->court_id = null;
        $game->save();
        
        Log::debug('Game left in queue', [
            'game_id' => $game->id,
            'game_number' => $game->game_number
        ]);
    }
    
    Log::info('Court assignment completed', [
        'games_with_court' => $games->take($courts->count())->count(),
        'games_in_queue' => $games->skip($courts->count())->count()
    ]);
}


public function generateStageGames(Session $session): Collection
{
    if (!$session->isTournament()) {
        Log::warning('Attempted to generate stage games for non-tournament session', [
            'session_id' => $session->id,
            'session_type' => $session->session_type
        ]);
        return collect();
    }

    $template = $this->loadTemplate($session);
    
    if (!$template) {
        Log::warning('No template found for generating next stage', [
            'session_id' => $session->id,
            'current_stage' => $session->current_stage
        ]);
        return collect();
    }

    // ✅ CORREGIDO: Para Stage 1, ordenar por ID (orden de creación)
    // Para Stage 2+, ordenar por ranking
    if ($session->current_stage === 1) {
        $players = $session->players()->orderBy('id')->get();
    } else {
        $players = $session->players()->orderBy('current_rank')->get();
    }
    
    $games = collect();
    
    // Obtener el último game_number y sumar 1
    $lastGame = $session->games()->orderBy('game_number', 'desc')->first();
    $gameNumber = $lastGame ? $lastGame->game_number + 1 : 1;

    Log::info('Generating stage games', [
        'session_id' => $session->id,
        'stage' => $session->current_stage,
        'starting_game_number' => $gameNumber,
        'players_count' => $players->count(),
        'ordering' => $session->current_stage === 1 ? 'by_id' : 'by_rank'
    ]);


    

    foreach ($template['blocks'] as $block) {
        $blockStage = $this->getStageFromBlock($block['label']);
        
        // Solo generar juegos del stage actual
        if ($blockStage !== $session->current_stage) {
            continue;
        }

        Log::info('Processing block for stage', [
            'block_label' => $block['label'],
            'target_stage' => $session->current_stage,
            'rounds_count' => count($block['rounds'])
        ]);

        foreach ($block['rounds'] as $roundIndex => $round) {
            foreach ($round['courts'] as $courtIndex => $courtData) {
                $teamA = $courtData['A'];
                $teamB = $courtData['B'];

                // Obtener jugadores usando notación avanzada
                $team1Player1 = $this->getPlayerFromAdvancedNotation($teamA[0], $players, $session);
                $team1Player2 = $this->getPlayerFromAdvancedNotation($teamA[1], $players, $session);
                $team2Player1 = $this->getPlayerFromAdvancedNotation($teamB[0], $players, $session);
                $team2Player2 = $this->getPlayerFromAdvancedNotation($teamB[1], $players, $session);

                // Validar que todos los jugadores estén disponibles
                if (!$team1Player1 || !$team1Player2 || !$team2Player1 || !$team2Player2) {
                    Log::warning('Cannot generate game - missing players', [
                        'notation' => [$teamA, $teamB],
                        'session_id' => $session->id,
                        'stage' => $session->current_stage,
                        'round' => $roundIndex,
                        'court' => $courtIndex
                    ]);
                    continue;
                }

                $game = Game::create([
                    'session_id' => $session->id,
                    'game_number' => $gameNumber,
                    'stage' => $blockStage,
                    'status' => 'pending',
                    'team1_player1_id' => $team1Player1->id,
                    'team1_player2_id' => $team1Player2->id,
                    'team2_player1_id' => $team2Player1->id,
                    'team2_player2_id' => $team2Player2->id,
                ]);

                $games->push($game);
                $gameNumber++;

                Log::debug('Game created for stage', [
                    'game_number' => $game->game_number,
                    'stage' => $blockStage,
                    'team1' => [$team1Player1->display_name, $team1Player2->display_name],
                    'team2' => [$team2Player1->display_name, $team2Player2->display_name]
                ]);
            }
        }
    }

    // Asignar canchas a los primeros juegos
    $this->assignCourtsToGames($session, $games);

    Log::info('Stage games generation completed', [
        'session_id' => $session->id,
        'stage' => $session->current_stage,
        'games_created' => $games->count(),
        'final_game_number' => $gameNumber - 1
    ]);

    return $games;
}


 


/**
 * Obtener jugador desde notación avanzada (Winner/Loser/R1P#)
 */
private function getPlayerFromAdvancedNotation(string $notation, Collection $players, Session $session): ?Player
{
    // Notación de ranking simple (P1, P2, P3...)
    if (preg_match('/^P(\d+)$/', $notation, $matches)) {
        $position = (int)$matches[1];
        
        // ✅ CORREGIDO: Para Stage 1, usar orden de creación (id)
        if ($session->current_stage === 1) {
            // Ordenar por ID (orden de creación) y tomar la posición
            $sortedPlayers = $players->sortBy('id')->values();
            return $sortedPlayers->get($position - 1); // -1 porque P1 es índice 0
        }
        
        // ✅ Para Stage 2+, usar ranking actual
        return $players->where('current_rank', $position)->first();
    }

    // Notación de stage anterior (S1P1, S2P3...)
    if (preg_match('/^S(\d+)P(\d+)$/', $notation, $matches)) {
        $stage = (int)$matches[1];
        $rank = (int)$matches[2];
        
        // Obtener jugador con ese rank del stage anterior
        return $players->where('current_rank', $rank)->first();
    }

    // Notación de winner/loser (Winner of G1, Loser of SF1...)
    if (preg_match('/(Winner|Loser) of (G\d+|SF\d+)/', $notation, $matches)) {
        $resultType = $matches[1]; // Winner o Loser
        $gameRef = $matches[2]; // G1, SF1, etc.
        
        return $this->getPlayerFromGameResult($session, $gameRef, $resultType);
    }

    Log::warning('Unknown player notation', ['notation' => $notation]);
    return null;
}




/**
 * Obtener jugador basado en ranking de stage anterior
 */
private function getPlayerFromPreviousStageRank(Session $session, int $stage, int $rank): ?Player
{
    // Para Stage 2: usar rankings de Stage 1
    // Para Stage 3: usar rankings de Stage 2
    $previousStage = $stage - 1;
    
    // Los rankings se actualizan automáticamente después de cada stage
    // así que podemos usar current_rank directamente
    return $session->players()
        ->where('current_rank', $rank)
        ->first();
}

/**
 * Obtener jugador basado en resultado de juego específico
 */
private function getPlayerFromGameResult(Session $session, string $gameRef, string $resultType): ?Player
{
    // Buscar el juego por referencia (G1, SF1, etc.)
    $game = $this->findGameByReference($session, $gameRef);
    
    if (!$game || $game->status !== 'completed') {
        Log::warning('Referenced game not found or not completed', [
            'game_ref' => $gameRef,
            'result_type' => $resultType
        ]);
        return null;
    }

    $isWinner = $resultType === 'Winner';
    
    if ($isWinner) {
        return $game->winner_team === 1 
            ? $game->team1Player1 // O lógica más compleja para equipos
            : $game->team2Player1;
    } else {
        return $game->winner_team === 1 
            ? $game->team2Player1 
            : $game->team1Player1;
    }
}


private function getPlayerFromPreviousGame(int $gameNumber, string $resultType, Session $session): ?Player
{
    $previousGame = $session->games()
        ->where('game_number', $gameNumber)
        ->where('status', 'completed')
        ->first();

    if (!$previousGame) {
        Log::warning('Referenced game not found or not completed', [
            'game_number' => $gameNumber,
            'result_type' => $resultType
        ]);
        return null;
    }

    $isWinner = $resultType === 'Winner';
    
    if ($isWinner) {
        return $previousGame->winner_team === 1 
            ? $previousGame->team1Player1 // Podría necesitar lógica más compleja aquí
            : $previousGame->team2Player1;
    } else {
        return $previousGame->winner_team === 1 
            ? $previousGame->team2Player1 
            : $previousGame->team1Player1;
    }
}


/**
 * Obtener jugadores ordenados por ranking
 */
private function getRankedPlayers(Session $session): Collection
{
    return $session->players()
        ->orderBy('current_rank')
        ->get();
}

public function generatePlayoffBracket(Session $session): Collection
{
    $games = collect();
    $gameNumber = Game::where('session_id', $session->id)->max('game_number') + 1;

    if ($session->isPlayoff4()) {
        // ✅ ORDENAR POR RANK
        $topPlayers = $session->players()
            ->orderBy('current_rank', 'asc')
            ->limit(4)
            ->get();

        $team1 = collect([$topPlayers[0], $topPlayers[3]]);
        $team2 = collect([$topPlayers[1], $topPlayers[2]]);

        $game = $this->createGame($session, $gameNumber, null, $team1, $team2);
        $game->is_playoff_game = true;
        $game->playoff_round = 'final';
        $game->save();

        $games->push($game);
        
        Log::info('Playoff bracket P4 generado', [
            'final_game' => $gameNumber,
            'top_4' => $topPlayers->pluck('display_name')
        ]);
    } 
    elseif ($session->isPlayoff8()) {
        // ✅ ORDENAR POR RANK
        $topPlayers = $session->players()
            ->orderBy('current_rank', 'asc')
            ->limit(8)
            ->get();

        $sf1Team1 = collect([$topPlayers[0], $topPlayers[7]]);
        $sf1Team2 = collect([$topPlayers[3], $topPlayers[4]]);

        $sf1 = $this->createGame($session, $gameNumber, null, $sf1Team1, $sf1Team2);
        $sf1->is_playoff_game = true;
        $sf1->playoff_round = 'semifinal';
        $sf1->save();
        $games->push($sf1);
        $gameNumber++;

        $sf2Team1 = collect([$topPlayers[1], $topPlayers[6]]);
        $sf2Team2 = collect([$topPlayers[2], $topPlayers[5]]);

        $sf2 = $this->createGame($session, $gameNumber, null, $sf2Team1, $sf2Team2);
        $sf2->is_playoff_game = true;
        $sf2->playoff_round = 'semifinal';
        $sf2->save();
        $games->push($sf2);

        Log::info('Playoff bracket P8 generado (semifinals)', [
            'semifinals' => [$gameNumber - 1, $gameNumber],
            'top_8' => $topPlayers->pluck('display_name')
        ]);
    }

    // ❌ NO ASIGNAR CANCHAS AQUÍ
    // $this->assignCourtsToGames($session, $games);

    return $games;
}

public function generateP8Finals(Session $session, Collection $semifinals): Collection
{
    $games = collect();
    $gameNumber = Game::where('session_id', $session->id)->max('game_number') + 1;

    $sf1 = $semifinals->first();
    $sf2 = $semifinals->last();

    $sf1Winner = $this->getWinningPlayers($sf1);
    $sf1Loser = $this->getLosingPlayers($sf1);

    $sf2Winner = $this->getWinningPlayers($sf2);
    $sf2Loser = $this->getLosingPlayers($sf2);

    $goldGame = $this->createGame($session, $gameNumber, null, $sf1Winner, $sf2Winner);
    $goldGame->is_playoff_game = true;
    $goldGame->playoff_round = 'gold';
    $goldGame->save();
    $games->push($goldGame);
    $gameNumber++;

    $bronzeGame = $this->createGame($session, $gameNumber, null, $sf1Loser, $sf2Loser);
    $bronzeGame->is_playoff_game = true;
    $bronzeGame->playoff_round = 'bronze';
    $bronzeGame->save();
    $games->push($bronzeGame);

    Log::info('P8 finals generated', [
        'gold_game' => $goldGame->game_number,
        'bronze_game' => $bronzeGame->game_number
    ]);

    // ❌ NO ASIGNAR CANCHAS AQUÍ
    $this->assignCourtsToGames($session, $games);

    $session->updateProgress();

    return $games;
}
    private function getWinningPlayers(Game $game): Collection
    {
        if ($game->winner_team === 1) {
            return collect([$game->team1Player1, $game->team1Player2]);
        }
        return collect([$game->team2Player1, $game->team2Player2]);
    }

    private function getLosingPlayers(Game $game): Collection
    {
        if ($game->winner_team === 1) {
            return collect([$game->team2Player1, $game->team2Player2]);
        }
        return collect([$game->team1Player1, $game->team1Player2]);
    }

    private function generateRandomGames(Session $session): Collection
    {
        $players = $session->players->shuffle();
        $games = collect();
        $gameNumber = 1;

        $estimatedGamesPerCourt = $session->duration_hours * 4;
        $totalGames = $session->number_of_courts * $estimatedGamesPerCourt;
        $minGamesPerPlayer = ceil($totalGames / ($session->number_of_players / 4));

        $playerGameCount = $players->mapWithKeys(fn($p) => [$p->id => 0])->toArray();

        for ($i = 0; $i < $totalGames && $this->canGenerateMoreGames($playerGameCount, $minGamesPerPlayer); $i++) {
            $availablePlayers = $players->filter(function($p) use ($playerGameCount, $minGamesPerPlayer) {
                return $playerGameCount[$p->id] < $minGamesPerPlayer;
            })->shuffle();

            if ($availablePlayers->count() >= 4) {
                $team1 = $availablePlayers->splice(0, 2);
                $team2 = $availablePlayers->splice(0, 2);

                $games->push($this->createGame($session, $gameNumber, null, $team1, $team2));
                
                foreach ($team1->concat($team2) as $player) {
                    $playerGameCount[$player->id]++;
                }

                $gameNumber++;
            }
        }

        $this->assignCourtsToGames($session, $games);

        return $games;
    }

    private function createGame(Session $session, int $gameNumber, ?int $stage, Collection $team1, Collection $team2): Game
    {
        return Game::create([
            'session_id' => $session->id,
            'game_number' => $gameNumber,
            'stage' => $stage,
            'status' => 'pending',
            'team1_player1_id' => $team1[0]->id,
            'team1_player2_id' => $team1[1]->id,
            'team2_player1_id' => $team2[0]->id,
            'team2_player2_id' => $team2[1]->id,
        ]);
    }

    private function canGenerateMoreGames(array $playerGameCount, int $minGamesPerPlayer): bool
    {
        return count(array_filter($playerGameCount, fn($count) => $count < $minGamesPerPlayer)) >= 4;
    }
}