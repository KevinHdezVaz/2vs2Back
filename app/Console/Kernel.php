<?php

namespace App\Console;

use App\Console\Commands\ResetMvpCounters;
use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\CancelIncompleteMatches;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected $commands = [
        CancelIncompleteMatches::class,
        ResetMvpCounters::class,

    ];

    protected function schedule(Schedule $schedule): void
    {   
       
            // ▼▼▼ ESTA ES LA ÚNICA LÍNEA QUE NECESITAS PARA EL WORKER ▼▼▼
            $schedule->command('queue:work --queue=images,default --stop-when-empty')->everyMinute();
    
            // ... (resto de tus tareas programadas)
            $schedule->command('notifications:streak-check')
                ->dailyAt('12:00')
                ->timezone(config('app.timezone'))
                ->withoutOverlapping()
                ->sendOutputTo(storage_path('logs/streak-notifications.log'));
    
            // LA LÍNEA ANTIGUA DEBE SER ELIMINADA
    

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
