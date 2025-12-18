<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ✅ AGREGAR ESTE IMPORT


class Session extends Model
{
 protected $fillable = [
    'firebase_id',
    'session_code',
    'moderator_code',      // ← AGREGADO
    'verification_code',
    'user_id',
    'session_name',
    'number_of_courts',
    'duration_hours',
    'number_of_players',
    'points_per_game',
    'win_by',              // ← CORREGIDO
    'number_of_sets',
    'session_type',
    'current_stage',
    'status',
    'started_at',
    'completed_at',
    'progress_percentage',
    'total_games'
];


    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percentage' => 'float'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * ✅ NUEVO: Generar código de sesión único (6 caracteres)
     */
    public static function generateUniqueCode(): string
    {
        do {
            $letters = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 2));
            $numbers = substr(str_shuffle('123456789'), 0, 4);
            $code = $letters . $numbers;
        } while (self::where('session_code', $code)->exists());

        return $code;
    }

    /**
     * ✅ NUEVO: Generar código de verificación (2 dígitos)
     */
    public static function generateVerificationCode(): string
    {
        return str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
    }


    /**
     * ✅ NUEVO: Validar código de verificación
     */
    public function validateVerificationCode(string $code): bool
    {
        return $this->verification_code === $code;
    }

    /**
     * ✅ NUEVO: Helper para saber si está en borrador
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * ✅ NUEVO: Activar sesión (de draft/pending a active)
     */
    public function activate(): void
    {
        if ($this->status !== 'active') {
            $this->status = 'active';
            $this->started_at = now();
            $this->save();

            Log::info('Session activated', [
                'session_id' => $this->id,
                'session_code' => $this->session_code,
                'verification_code' => $this->verification_code
            ]);
        }
    }

    /**
     * ✅ MÉTODO EXISTENTE - Sin cambios
     */
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

    public function isSimple(): bool
{
    return $this->session_type === 'S';
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
    // ✅ Query optimizada - SIN display_name (no existe en la tabla)
    $players = $this->players()
        ->select('id', 'current_rating') // ← QUITAR 'display_name'
        ->orderBy('current_rating', 'desc')
        ->get();

    if ($players->isEmpty()) {
        return;
    }

    // ✅ Preparar bulk update con CASE
    $cases = [];
    $ids = [];

    foreach ($players as $index => $player) {
        $rank = $index + 1;
        $cases[] = "WHEN {$player->id} THEN {$rank}";
        $ids[] = $player->id;
    }

    $casesSql = implode(' ', $cases);
    $idsSql = implode(',', $ids);

    // ✅ 1 solo UPDATE para todos los jugadores
    \DB::update("
        UPDATE players
        SET current_rank = CASE id {$casesSql} END,
            updated_at = NOW()
        WHERE id IN ({$idsSql})
    ");

    Log::info('Rankings updated', [
        'session_id' => $this->id,
        'players_count' => count($ids)
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

  public function updateProgress(): void
{
    // ✅ Cachear total_games si ya se calculó
    if ($this->total_games === null || $this->total_games === 0) {
        $this->total_games = $this->calculateTotalExpectedGames();
    }

    // ✅ Query única
    $completedGames = $this->games()
        ->where('status', 'completed')
        ->count();

    if ($this->total_games > 0) {
        $this->progress_percentage = ($completedGames / $this->total_games) * 100;
    } else {
        $this->progress_percentage = 0;
    }

    // ✅ updateQuietly evita eventos (más rápido)
    $this->updateQuietly([
        'progress_percentage' => round($this->progress_percentage, 2),
        'total_games' => $this->total_games
    ]);
}


    private function calculateTotalExpectedGames(): int
{
    // ✅ P8 ESPECIAL (1C2H6P-P8, 1C2H7P-P8 futuros)
    if ($this->isSpecialP8()) {
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();

        // Qualifier + Final = 2 juegos de playoff
        return $regularGames + 2;
    }

    // ✅ P8 NORMAL
    if ($this->isPlayoff8()) {
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();

        // 2 Semifinals + Gold + Bronze = 4 juegos de playoff
        return $regularGames + 4;
    }

    // ✅ P4 - CORREGIDO: Verificar si tiene Bronze match (2+ canchas)
    if ($this->isPlayoff4()) {
        $regularGames = $this->games()
            ->where('is_playoff_game', false)
            ->count();

        // ✅ VERIFICAR SI EXISTE BRONZE MATCH
        $hasBronzeMatch = $this->games()
            ->where('is_playoff_game', true)
            ->where('playoff_round', 'bronze')
            ->exists();

        // Gold (siempre) + Bronze (si hay 2+ canchas)
        $playoffGames = $hasBronzeMatch ? 2 : 1;

        return $regularGames + $playoffGames;
    }

    // ✅ TOURNAMENT
    if ($this->isTournament()) {
        $template = $this->loadTemplateForSession();

        if ($template && isset($template['blocks'])) {
            $totalGames = 0;

            foreach ($template['blocks'] as $block) {
                foreach ($block['rounds'] as $round) {
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

        Log::warning('⚠️ Template not found for tournament - using current games count', [
            'session_id' => $this->id,
            'template_name' => $this->getTemplateName()
        ]);

        return $this->games()->count();
    }

    // ✅ SIMPLE / OPTIMIZED
    return $this->games()->count();
}


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
