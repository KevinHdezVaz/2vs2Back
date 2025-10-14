<?php
namespace App\Console\Commands;

use App\Models\UserProfile;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log; // Para depuración

class SendStreakNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:streak-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks user streaks and sends daily push notifications via OneSignal.';

    protected $oneSignalService;

    public function __construct(OneSignalService $oneSignalService)
    {
        parent::__construct();
        $this->oneSignalService = $oneSignalService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Iniciando el chequeo de rachas para notificaciones...');

        $today = Carbon::now(); // Usa la zona horaria de la aplicación (config/app.php)

        // Obtener todos los perfiles de usuario que tienen un plan completado
        // y un Player ID de OneSignal.
        $profiles = UserProfile::where('plan_setup_complete', true)
                               ->whereNotNull('onesignal_player_id')
                               ->get();

        $this->info("Chequeando rachas para " . $profiles->count() . " usuarios.");

        foreach ($profiles as $profile) {
            // Log::info("Procesando usuario: {$profile->user_id}");

            $lastStreakDate = $profile->ultima_fecha_racha; // Esto ya es un objeto Carbon gracias a $casts

            $daysSinceLastStreak = 999; // Valor por defecto alto

            if ($lastStreakDate) {
                // Calcula la diferencia en días.
                // Si ultima_fecha_racha es 'ayer' y hoy es 'hoy', diffInDays será 1.
                // Si es 'hoy' (ya completó), diffInDays será 0.
                $daysSinceLastStreak = $today->diffInDays($lastStreakDate);
                // Si la última fecha es posterior a hoy (error o zona horaria), ignora.
                if ($lastStreakDate->isAfter($today)) {
                    $daysSinceLastStreak = -1;
                }
            } else {
                // Si no hay ultima_fecha_racha, es un nuevo usuario o racha no iniciada.
                // Podríamos considerar enviar una notificación de "¡Es hora de empezar tu racha!".
                // Por ahora, lo saltamos y la lógica de la app lo manejará.
                // Para efectos de notificación de "no cumplió", si no hay fecha, asumimos que no cumplió "ayer".
                $daysSinceLastStreak = 1; // Para que caiga en la lógica de "cumple hoy"
            }

            $heading = "¡Frutia te recuerda!";
            $message = "";
            $sendNotification = false;

            // Lógica para determinar el mensaje a enviar
            if ($daysSinceLastStreak == 0) {
                // Ya completó el día de hoy, no se envía recordatorio.
                Log::info("Usuario {$profile->user_id}: Ya completó su racha hoy.");
                continue; // Pasa al siguiente usuario
            } elseif ($daysSinceLastStreak == 1) {
                // No ha completado hoy, pero ayer sí lo hizo. Recordatorio diario.
                $message = "¡Cumple tu racha hoy! Te estamos esperando para sumar un día más.";
                $sendNotification = true;
            } elseif ($daysSinceLastStreak == 2) {
                // No ha completado hoy ni ayer. Racha en peligro inminente.
                $message = "¡Tu racha está en peligro! Llevas 2 días sin registrar. ¡Completa hoy para salvarla!";
                $sendNotification = true;
            } elseif ($daysSinceLastStreak == 3) {
                // No ha completado hoy, ayer, ni antes de ayer. Última llamada.
                $message = "¡Última oportunidad! Tu racha está a punto de romperse. ¡Completa tu día AHORA!";
                $sendNotification = true;
            } else {
                // $daysSinceLastStreak >= 4: Racha ya perdida, no enviamos más avisos de peligro.
                // La app ya mostrará la racha en 0.
                Log::info("Usuario {$profile->user_id}: Racha perdida por más de 3 días, no se envía notificación de peligro.");
                continue; // Pasa al siguiente usuario
            }

            if ($sendNotification) {
                $success = $this->oneSignalService->sendNotification(
                    $profile->onesignal_player_id,
                    $heading,
                    $message,
                    ['type' => 'streak_reminder'] // Datos adicionales para la app
                );

                if ($success) {
                    $this->info("Notificación de racha enviada a usuario {$profile->user_id}");
                } else {
                    $this->error("Fallo al enviar notificación de racha a usuario {$profile->user_id}");
                }
            }
        }

        $this->info('Chequeo de rachas finalizado.');
        Log::info('Chequeo de rachas finalizado.');
    }
}