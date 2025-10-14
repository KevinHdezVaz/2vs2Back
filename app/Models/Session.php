<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    protected $fillable = [
        'firebase_id',
        'user_id',
        'session_name',
        'number_of_courts',
        'duration_hours',
        'number_of_players',
        'points_per_game',
        'win_by',
        'number_of_sets',
        'session_type', // T, P4, P8
        'current_stage', // 1, 2, 3 (for Tournament)
        'status', // pending, active, completed
        'started_at',
        'completed_at',
        'progress_percentage'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percentage' => 'float'
    ];

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    // Helper methods
    public function isTournament(): bool
    {
        return $this->session_type === 'T';
    }

    public function isPlayoff4(): bool
    {
        return $this->session_type === 'P4';
    }

    public function isPlayoff8(): bool
    {
        return $this->session_type === 'P8';
    }

  // En App\Models\Session
public function canAdvanceStage(): bool
{
    if ($this->isTournament()) {
        // Verificar que todos los juegos del stage actual estén completos
        return $this->games()
            ->where('stage', $this->current_stage)
            ->where('status', '!=', 'completed')
            ->count() === 0;
    }
    
    if ($this->isPlayoff4() || $this->isPlayoff8()) {
        // Verificar que todos los juegos regulares estén completos
        // Y que no haya juegos de playoff ya generados
        $regularGamesComplete = $this->games()
            ->where('is_playoff_game', false)
            ->where('status', '!=', 'completed')
            ->count() === 0;
            
        $noPlayoffGames = !$this->games()
            ->where('is_playoff_game', true)
            ->exists();
            
        return $regularGamesComplete && $noPlayoffGames;
    }
    
    return false;
}

// En App\Models\Session - método updateRankings()
public function updateRankings(): void
{
    // Obtener todos los jugadores con sus estadísticas
    $players = $this->players()->get();

    // Calcular stats para cada jugador
    foreach ($players as $player) {
        $player->updateStats($this->id); // Esto llamará al nuevo calculateStats()
    }

    // ✅ ORDENAR CON CRITERIOS DE DESEMPATE
    $rankedPlayers = $players->sortByDesc(function ($player) {
        return [
            $player->current_rating ?? 0,
            $player->win_percentage ?? 0,
            $player->points_won_percentage ?? 0,
        ];
    })->sortBy(function ($player) {
        return strtolower($player->first_name);
    })->values();

    // ✅ Asignar ranks
    $currentRank = 1;
    $previousRating = null;
    $previousWinPct = null;
    $previousPtsPct = null;

    foreach ($rankedPlayers as $index => $player) {
        // Si los criterios son iguales al anterior, mantener el mismo rank
        if ($previousRating === $player->current_rating &&
            $previousWinPct === $player->win_percentage &&
            $previousPtsPct === $player->points_won_percentage) {
            // Mismo rank
        } else {
            $currentRank = $index + 1;
        }

        $player->current_rank = $currentRank;
        $player->save();

        // Guardar valores para comparar con el siguiente
        $previousRating = $player->current_rating;
        $previousWinPct = $player->win_percentage;
        $previousPtsPct = $player->points_won_percentage;
    }
}



public function isFullyCompleted(): bool
{

      if ($this->isTournament()) {
        // Si NO está en Stage 3, NO puede estar completado
        if ($this->current_stage < 3) {
            return false;
        }
        
        // Si está en Stage 3, verificar que no haya juegos pending/active
        $pendingActiveGames = $this->games()
            ->whereIn('status', ['pending', 'active'])
            ->count();
        
        return $pendingActiveGames === 0;
    }

    // ✅ PARA P8: Verificar que existan Y estén completadas las finals
    if ($this->isPlayoff8()) {
        $goldGame = $this->games()
            ->where('is_playoff_game', true)
            ->where('playoff_round', 'gold')
            ->first();
            
        $bronzeGame = $this->games()
            ->where('is_playoff_game', true)
            ->where('playoff_round', 'bronze')
            ->first();
        
        // Si NO existen las finals, NO está completada
        if (!$goldGame || !$bronzeGame) {
            return false;
        }
        
        // Si existen pero NO están completadas, NO está completada
        if ($goldGame->status !== 'completed' || $bronzeGame->status !== 'completed') {
            return false;
        }
    }
    
    // ✅ PARA P4: Verificar que exista Y esté completada la final
    if ($this->isPlayoff4()) {
        $finalGame = $this->games()
            ->where('is_playoff_game', true)
            ->where('playoff_round', 'final')
            ->first();
        
        // Si NO existe la final, NO está completada
        if (!$finalGame) {
            return false;
        }
        
        // Si existe pero NO está completada, NO está completada
        if ($finalGame->status !== 'completed') {
            return false;
        }
    }
    
   // Para todos los casos: verificar que no haya juegos pending/active
    $pendingActiveGames = $this->games()
        ->whereIn('status', ['pending', 'active'])
        ->count();
    
    return $pendingActiveGames === 0;
}



    public function updateProgress(): void
    {
        $totalGames = $this->games()->count();
        $completedGames = $this->games()->where('status', 'completed')->count();
        
        $this->progress_percentage = $totalGames > 0 
            ? ($completedGames / $totalGames) * 100 
            : 0;
        
        $this->save();
    }
}