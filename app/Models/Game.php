<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'session_id',
        'court_id',
        'stage',
        'status',
        'game_number',
        'team1_player1_id',
        'team1_player2_id',
        'team2_player1_id',
        'team2_player2_id',
        'team1_score',
        'team2_score',
        'team1_set1_score',
        'team2_set1_score',
        'team1_set2_score',
        'team2_set2_score',
        'team1_set3_score',
        'team2_set3_score',
        'team1_sets_won',
        'team2_sets_won',
        'winner_team',
        'started_at',
        'completed_at',
        'is_playoff_game',
        'playoff_round',
        'metadata'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_playoff_game' => 'boolean',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
        'team1_set1_score' => 'integer',
        'team2_set1_score' => 'integer',
        'team1_set2_score' => 'integer',
        'team2_set2_score' => 'integer',
        'team1_set3_score' => 'integer',
        'team2_set3_score' => 'integer',
        'team1_sets_won' => 'integer',
        'team2_sets_won' => 'integer'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function team1Player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'team1_player1_id');
    }

    public function team1Player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'team1_player2_id');
    }

    public function team2Player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'team2_player1_id');
    }

    public function team2Player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'team2_player2_id');
    }

    public function getTeam1PlayersAttribute(): array
    {
        return [
            $this->team1Player1,
            $this->team1Player2
        ];
    }

    public function getTeam2PlayersAttribute(): array
    {
        return [
            $this->team2Player1,
            $this->team2Player2
        ];
    }

    public function getAllPlayerIds(): array
    {
        return [
            $this->team1_player1_id,
            $this->team1_player2_id,
            $this->team2_player1_id,
            $this->team2_player2_id
        ];
    }

    /**
     * ✅ NUEVO: Validar score según el formato (Best of 1 o Best of 3)
     */
    public function isScoreValid(): bool
    {
        $session = $this->session;
        
        // Determinar si es Best of 3
        if ($session->number_of_sets === '3') {
            return $this->isScoreValidBestOf3();
        }
        
        // Best of 1 (lógica original)
        return $this->isScoreValidBestOf1();
    }

    /**
     * Validación para Best of 1 (lógica original)
     */
    private function isScoreValidBestOf1(): bool
    {
        $session = $this->session;

        if ($this->team1_score === $this->team2_score) {
            return false;
        }

        $winnerScore = max($this->team1_score, $this->team2_score);
        $loserScore = min($this->team1_score, $this->team2_score);

        if ($winnerScore < $session->points_per_game) {
            return false;
        }

        if (($winnerScore - $loserScore) < $session->win_by) {
            return false;
        }

        return true;
    }

    /**
     * ✅ NUEVO: Validación para Best of 3
     */
    private function isScoreValidBestOf3(): bool
    {
        $session = $this->session;

        // Verificar que haya scores de sets
        if ($this->team1_set1_score === null || $this->team2_set1_score === null ||
            $this->team1_set2_score === null || $this->team2_set2_score === null) {
            return false;
        }

        // Validar cada set individualmente
        if (!$this->isSetValid($this->team1_set1_score, $this->team2_set1_score, $session)) {
            return false;
        }
        
        if (!$this->isSetValid($this->team1_set2_score, $this->team2_set2_score, $session)) {
            return false;
        }

        // Contar sets ganados
        $team1SetsWon = 0;
        $team2SetsWon = 0;

        if ($this->team1_set1_score > $this->team2_set1_score) {
            $team1SetsWon++;
        } else {
            $team2SetsWon++;
        }

        if ($this->team1_set2_score > $this->team2_set2_score) {
            $team1SetsWon++;
        } else {
            $team2SetsWon++;
        }

        // Si hay empate 1-1, DEBE haber tercer set
        if ($team1SetsWon === 1 && $team2SetsWon === 1) {
            if ($this->team1_set3_score === null || $this->team2_set3_score === null) {
                return false;
            }
            
            if (!$this->isSetValid($this->team1_set3_score, $this->team2_set3_score, $session)) {
                return false;
            }

            if ($this->team1_set3_score > $this->team2_set3_score) {
                $team1SetsWon++;
            } else {
                $team2SetsWon++;
            }
        }

        // Alguien debe haber ganado 2 sets
        return ($team1SetsWon === 2 || $team2SetsWon === 2);
    }

    /**
     * ✅ NUEVO: Validar un set individual
     */
    private function isSetValid(int $score1, int $score2, $session): bool
    {
        // No empates
        if ($score1 === $score2) {
            return false;
        }

        $winnerScore = max($score1, $score2);
        $loserScore = min($score1, $score2);
        $scoreDiff = $winnerScore - $loserScore;

        $pointsPerGame = $session->points_per_game;
        $winBy = $session->win_by;

        // Validación según win_by
        if ($winBy == 2) {
            // Caso A: Ganador tiene exactamente pointsPerGame
            if ($winnerScore == $pointsPerGame) {
                if ($loserScore > $pointsPerGame - 2) {
                    return false;
                }
            }
            // Caso B: Ganador tiene más de pointsPerGame (juego extendido)
            elseif ($winnerScore > $pointsPerGame) {
                if ($loserScore < $pointsPerGame - 1) {
                    return false;
                }
                if ($scoreDiff != 2) {
                    return false;
                }
                if ($winnerScore > $pointsPerGame + 10) {
                    return false;
                }
            }
            // Caso C: Ganador tiene menos de pointsPerGame - INVÁLIDO
            else {
                return false;
            }
        }

        if ($winBy == 1) {
            if ($winnerScore < $pointsPerGame) {
                return false;
            }
            if ($scoreDiff < 1) {
                return false;
            }
            if ($winnerScore == $pointsPerGame && $loserScore >= $pointsPerGame) {
                return false;
            }
            if ($winnerScore > $pointsPerGame + 10) {
                return false;
            }
        }

        return true;
    }

    public function markAsCompleted(int $team1Score, int $team2Score): void
    {
        $this->team1_score = $team1Score;
        $this->team2_score = $team2Score;
        $this->winner_team = $team1Score > $team2Score ? 1 : 2;
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }
}   