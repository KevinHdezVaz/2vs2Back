<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Player;

class RatingService
{
    // Factor K - determina cuánto pueden cambiar los ratings
    private const K_FACTOR = 32;
    
    // Factor de ajuste por margen de puntos
    private const SCORE_MARGIN_FACTOR = 0.1;

    /**
     * Actualizar ratings después de un juego completado
     */
    public function updateRatings(Game $game): void
    {
        // Obtener jugadores
        $team1Players = [$game->team1Player1, $game->team1Player2];
        $team2Players = [$game->team2Player1, $game->team2Player2];

        // Calcular rating promedio de cada equipo
        $team1AvgRating = ($team1Players[0]->current_rating + $team1Players[1]->current_rating) / 2;
        $team2AvgRating = ($team2Players[0]->current_rating + $team2Players[1]->current_rating) / 2;

        // Calcular probabilidades esperadas
        $team1ExpectedScore = $this->calculateExpectedScore($team1AvgRating, $team2AvgRating);
        $team2ExpectedScore = 1 - $team1ExpectedScore;

        // Resultado real (1 si ganó, 0 si perdió)
        $team1ActualScore = $game->winner_team === 1 ? 1 : 0;
        $team2ActualScore = $game->winner_team === 2 ? 1 : 0;

        // Calcular multiplicador basado en margen de puntos
        $scoreMargin = abs($game->team1_score - $game->team2_score);
        $marginMultiplier = $this->calculateMarginMultiplier($scoreMargin, $game->session->points_per_game);

        // Calcular cambio de rating base
        $team1RatingChange = self::K_FACTOR * ($team1ActualScore - $team1ExpectedScore) * $marginMultiplier;
        $team2RatingChange = self::K_FACTOR * ($team2ActualScore - $team2ExpectedScore) * $marginMultiplier;

        // Aplicar cambios a cada jugador del equipo
        foreach ($team1Players as $player) {
            $player->current_rating += $team1RatingChange;
            $player->save();
        }

        foreach ($team2Players as $player) {
            $player->current_rating += $team2RatingChange;
            $player->save();
        }
    }

    /**
     * Calcular probabilidad esperada usando fórmula ELO
     */
    private function calculateExpectedScore(float $ratingA, float $ratingB): float
    {
        return 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    }

    /**
     * Calcular multiplicador basado en el margen de puntos
     * Victorias más contundentes generan mayores cambios de rating
     */
    private function calculateMarginMultiplier(int $margin, int $maxPoints): float
    {
        // Normalizar el margen (0 a 1)
        $normalizedMargin = min($margin / $maxPoints, 1);
        
        // Aplicar curva: mínimo 0.5, máximo 1.5
        return 0.5 + ($normalizedMargin * 1.0);
    }

    /**
     * Calcular rating inicial basado en nivel del jugador
     */
    public function getInitialRating(string $level): float
    {
        return match($level) {
            'Por encima del promedio' => 1200.0,
            'Promedio' => 1000.0,
            'Por debajo del promedio' => 800.0,
            default => 1000.0
        };
    }

    /**
     * Recalcular todos los ratings desde cero (útil para debugging)
     */
    public function recalculateAllRatings(int $sessionId): void
    {
        // Resetear ratings a iniciales
        $players = Player::where('session_id', $sessionId)->get();
        foreach ($players as $player) {
            $player->current_rating = $player->initial_rating;
            $player->save();
        }

        // Recalcular en orden cronológico
        $games = Game::where('session_id', $sessionId)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->get();

        foreach ($games as $game) {
            $this->updateRatings($game);
        }
    }

    /**
     * Obtener distribución de ratings en la sesión
     */
    public function getRatingDistribution(int $sessionId): array
    {
        $players = Player::where('session_id', $sessionId)
            ->orderBy('current_rating', 'desc')
            ->get();

        return [
            'highest' => $players->first()?->current_rating ?? 0,
            'lowest' => $players->last()?->current_rating ?? 0,
            'average' => $players->avg('current_rating') ?? 0,
            'median' => $this->calculateMedian($players->pluck('current_rating')->toArray()),
            'distribution' => $players->map(fn($p) => [
                'player' => $p->display_name,
                'rating' => round($p->current_rating, 2)
            ])
        ];
    }

    /**
     * Calcular mediana
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle] + $values[$middle + 1]) / 2;
    }
}