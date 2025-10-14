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

    public function updateStats(): void
    {
        if ($this->games_played > 0) {
            $this->win_percentage = ($this->games_won / $this->games_played) * 100;
        }

        $totalPoints = $this->total_points_won + $this->total_points_lost;
        if ($totalPoints > 0) {
            $this->points_won_percentage = ($this->total_points_won / $totalPoints) * 100;
        }

        $this->save();
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
}