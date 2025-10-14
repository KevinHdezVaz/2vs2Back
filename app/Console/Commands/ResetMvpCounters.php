<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserStats;

class ResetMvpCounters extends Command
{
    protected $signature = 'mvp:reset-counters';
    protected $description = 'Reinicia los contadores de MVP cada mes';

    public function handle()
    {
        // Reiniciar el contador de MVP para todos los usuarios
        UserStats::query()->update(['mvp_count' => 0]);

        $this->info('Contadores de MVP reiniciados exitosamente.');
    }
}