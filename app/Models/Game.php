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
        'team1_sets_won',
        'team2_sets_won',
        'winner_team',
        'started_at',
        'completed_at',
        'is_playoff_game',
        'playoff_round'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_playoff_game' => 'boolean',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
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

    public function isScoreValid(): bool
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