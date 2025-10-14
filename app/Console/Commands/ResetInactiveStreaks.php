<?php
// en app/Console/Commands/ResetInactiveStreaks.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResetInactiveStreaks extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     * @var string
     */
    protected $signature = 'streaks:reset-inactive';

    /**
     * La descripción del comando de consola.
     * @var string
     */
    protected $description = 'Reinicia a 0 las rachas de los usuarios que no cumplieron su día ayer.';

    /**
     * Ejecuta el comando de consola.
     *
     * @return int
     */
    public function handle()
    {
        // La fecha límite es "anteayer". Cualquier racha cuya última actualización
        // sea de anteayer o más antigua, significa que ayer no se cumplió.
        $fechaLimite = Carbon::yesterday('UTC')->subDay()->toDateString();
        
        $this->info('Buscando rachas inactivas para reiniciar...');
        Log::info('Iniciando tarea programada: ResetInactiveStreaks.');

        // Buscamos perfiles que:
        // 1. Tienen una racha activa (mayor a 0).
        // 2. Su última actualización fue ANTES de ayer.
        $streaksReiniciadas = UserProfile::where('racha_actual', '>', 0)
            ->whereDate('ultima_fecha_racha', '<=', $fechaLimite)
            ->update(['racha_actual' => 0]);

        $this->info("¡Tarea completada! Se reiniciaron {$streaksReiniciadas} rachas.");
        Log::info("ResetInactiveStreaks: Se reiniciaron {$streaksReiniciadas} rachas.");
        
        return 0;
    }
}