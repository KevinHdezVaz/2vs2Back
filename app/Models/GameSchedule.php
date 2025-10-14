// app/Models/GameSchedule.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSchedule extends Model
{
    protected $fillable = [
        'configuration',
        'courts',
        'players',
        'session_type',
        'stage',
        'schedule'
    ];

    protected $casts = [
        'schedule' => 'array'
    ];

    /**
     * Buscar schedule por configuración
     */
    public static function findSchedule(int $courts, int $players, string $sessionType, ?int $stage = null)
    {
        $config = "{$courts}C2H{$players}P-{$sessionType}";
        
        return self::where('configuration', $config)
            ->where('stage', $stage)
            ->first();
    }
}