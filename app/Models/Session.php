<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Session extends Model
{
    protected $fillable = [
        'firebase_id',
        'session_code',
        'user_id',
        'session_name',
        'number_of_courts',
        'duration_hours',
        'number_of_players',
        'points_per_game',
        'win_by',
        'number_of_sets',
        'session_type', // T, P4, P8, O
        'current_stage', // 1, 2, 3 (for Tournament)
        'status', // pending, active, completed
        'started_at',
        'completed_at',
        'progress_percentage',
        'total_games' // ✅ AGREGAR este campo
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

    public static function generateUniqueCode(): string
    {
        do {
            $letters = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 2));
            $numbers = substr(str_shuffle('123456789'), 0, 4);
            $code = $letters . $numbers;
        } while (self::where('session_code', $code)->exists());
        
        return $code;
    }

    public function canAdvanceStage(): bool
    {
        if ($this->isTournament()) {
            return $this->games()
                ->where('stage', $this->current_stage)
                ->where('status', '!=', 'completed')
                ->count() === 0;
        }
        
        if ($this->isPlayoff4() || $this->isPlayoff8()) {
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

    public function updateRankings(): void
    {
        $players = $this->players()->get();
        $rankedPlayers = $players->sortByDesc('current_rating')->values();

        foreach ($rankedPlayers as $index => $player) {
            $player->current_rank = $index + 1;
            $player->save();
        }

        Log::info('Rankings simplificados actualizados', [
            'session_id' => $this->id,
            'top_3_players' => $rankedPlayers->take(3)->map(function($p) {
                return [
                    'name' => $p->display_name,
                    'rating' => $p->current_rating,
                    'rank' => $p->current_rank
                ];
            })->toArray()
        ]);
    }

    public function isReadyToFinalize(): bool
    {
        return $this->status === 'active';
    }

    public function isFullyCompleted(): bool
    {
        if ($this->isTournament()) {
            if ($this->current_stage < 3) {
                return false;
            }
            
            $pendingActiveGames = $this->games()
                ->whereIn('status', ['pending', 'active'])
                ->count();
            
            return $pendingActiveGames === 0;
        }

        if ($this->isPlayoff8()) {
            $goldGame = $this->games()
                ->where('is_playoff_game', true)
                ->where('playoff_round', 'gold')
                ->first();
                
            $bronzeGame = $this->games()
                ->where('is_playoff_game', true)
                ->where('playoff_round', 'bronze')
                ->first();
            
            if (!$goldGame || !$bronzeGame) {
                return false;
            }
            
            if ($goldGame->status !== 'completed' || $bronzeGame->status !== 'completed') {
                return false;
            }
        }
        
        if ($this->isPlayoff4()) {
            $finalGame = $this->games()
                ->where('is_playoff_game', true)
                ->where('playoff_round', 'final')
                ->first();
            
            if (!$finalGame) {
                return false;
            }
            
            if ($finalGame->status !== 'completed') {
                return false;
            }
        }
        
        $pendingActiveGames = $this->games()
            ->whereIn('status', ['pending', 'active'])
            ->count();
        
        return $pendingActiveGames === 0;
    }

    // ✅ MÉTODO ACTUALIZADO
    public function updateProgress(): void
    {
        $completedGames = $this->games()->where('status', 'completed')->count();
        
        // ✅ CALCULAR TOTAL REAL DE JUEGOS según tipo de sesión
        $totalGames = $this->calculateTotalExpectedGames();
        
        if ($totalGames > 0) {
            $this->progress_percentage = ($completedGames / $totalGames) * 100;
        } else {
            $this->progress_percentage = 0;
        }
        
        $this->total_games = $totalGames;
        $this->save();
        
        Log::info('Progress updated', [
            'session_id' => $this->id,
            'session_type' => $this->session_type,
            'completed_games' => $completedGames,
            'total_expected_games' => $totalGames,
            'progress_percentage' => round($this->progress_percentage, 2)
        ]);
    }

    /**
     * ✅ NUEVO MÉTODO: Calcular total esperado de juegos según tipo de sesión
     */
  
    /**
 * ✅ CORREGIDO: Calcular total esperado de juegos según tipo de sesión
 */
private function calculateTotalExpectedGames(): int
{
    // ✅ PARA P8 ESPECIAL (1C2H6P-P8 o 1C2H7P-P8)
    if ($this->isSpecialP8()) {
        // Fase regular + 1 Qualifier + 1 Final
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();
        
        return $regularGames + 2; // +1 Qualifier, +1 Final
    }
    
    // ✅ PARA P8 NORMAL
    if ($this->isPlayoff8()) {
        // Fase regular + 2 Semifinals + 2 Finals (Gold + Bronze)
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();
        
        return $regularGames + 4; // +2 Semifinals, +1 Gold, +1 Bronze
    }
    
    // ✅ PARA P4
    if ($this->isPlayoff4()) {
        // Fase regular + 1 Final
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();
        
        return $regularGames + 1; // +1 Final
    }
    
    // ✅ PARA TOURNAMENT - CARGAR DESDE TEMPLATE
    if ($this->isTournament()) {
        $template = $this->loadTemplateForSession();
        
        if ($template && isset($template['blocks'])) {
            $totalGames = 0;
            
            // Contar todos los juegos en todos los bloques (stages)
            foreach ($template['blocks'] as $block) {
                foreach ($block['rounds'] as $round) {
                    // Cada round tiene N courts, cada court = 1 juego
                    $totalGames += count($round['courts']);
                }
            }
            
            Log::info('📊 Tournament total games from template', [
                'session_id' => $this->id,
                'template_name' => $this->getTemplateName(),
                'total_games_from_template' => $totalGames,
                'current_stage' => $this->current_stage,
                'blocks_count' => count($template['blocks'])
            ]);
            
            return $totalGames;
        }
        
        // Fallback: si no hay template, usar juegos actuales
        Log::warning('⚠️ Template not found for tournament - using current games count', [
            'session_id' => $this->id,
            'template_name' => $this->getTemplateName()
        ]);
        
        return $this->games()->count();
    }
    
    // ✅ PARA OPTIMIZED
    // Todos los juegos ya están generados al inicio
    return $this->games()->count();
}

/**
 * ✅ NUEVO MÉTODO: Cargar template desde JSON (reutiliza lógica del GameGeneratorService)
 */
private function loadTemplateForSession(): ?array
{
    $filename = $this->getTemplateName();
    $path = storage_path("app/game_templates/{$filename}.json");

    if (!file_exists($path)) {
        Log::warning('Template file not found', [
            'session_id' => $this->id,
            'template_name' => $filename,
            'path' => $path
        ]);
        return null;
    }

    try {
        $content = file_get_contents($path);
        $template = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Error decoding template JSON', [
                'session_id' => $this->id,
                'template_name' => $filename,
                'error' => json_last_error_msg()
            ]);
            return null;
        }
        
        return $template;
    } catch (\Exception $e) {
        Log::error('Error loading template', [
            'session_id' => $this->id,
            'template_name' => $filename,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * ✅ NUEVO MÉTODO: Obtener nombre del template
 */
private function getTemplateName(): string
{
    return sprintf(
        '%dC%dH%dP-%s',
        $this->number_of_courts,
        $this->duration_hours,
        $this->number_of_players,
        $this->session_type
    );
}

    /**
     * ✅ NUEVO MÉTODO: Detectar si es P8 especial
     */
    private function isSpecialP8(): bool
    {
        if (!$this->isPlayoff8()) {
            return false;
        }
        
        $specialTemplates = ['1C2H6P-P8', '1C2H7P-P8'];
        $templateName = sprintf(
            '%dC%dH%dP-%s',
            $this->number_of_courts,
            $this->duration_hours,
            $this->number_of_players,
            $this->session_type
        );
        
        return in_array($templateName, $specialTemplates);
    }
}