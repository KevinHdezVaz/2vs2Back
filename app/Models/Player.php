<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    protected $fillable = [
        'session_id',
        'first_name',
        'last_initial',
        'level',
        'dominant_hand',
        'initial_rating',
        'current_rating',
        'current_rank',
        'games_played',
        'games_won',
        'games_lost',
        'total_points_won',
        'total_points_lost',
        'win_percentage',
        'points_won_percentage'
    ];

    protected $casts = [
        'initial_rating' => 'float',
        'current_rating' => 'float',
        'current_rank' => 'integer',
        'games_played' => 'integer',
        'games_won' => 'integer',
        'games_lost' => 'integer',
        'total_points_won' => 'integer',
        'total_points_lost' => 'integer',
        'win_percentage' => 'float',
        'points_won_percentage' => 'float'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_initial . '.';
    }

   
    public function getInitialRatingByLevel(): float
    {
        return match($this->level) {
            'Por encima del promedio' => 1200,
            'Promedio' => 1000,
            'Por debajo del promedio' => 800,
            default => 1000
        };
    }

    /**
     * Obtener todos los juegos del jugador
     */
    public function games()
    {
        return Game::where(function ($query) {
            $query->where('team1_player1_id', $this->id)
                ->orWhere('team1_player2_id', $this->id)
                ->orWhere('team2_player1_id', $this->id)
                ->orWhere('team2_player2_id', $this->id);
        });
    }

    /**
     * Obtener juegos completados del jugador
     */
    public function completedGames()
    {
        return $this->games()->where('status', 'completed');
    }
}