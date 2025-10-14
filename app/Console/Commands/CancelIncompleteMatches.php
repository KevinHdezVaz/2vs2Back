<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\DailyMatch;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\NotificationController;

class CancelIncompleteMatches extends Command
{
    protected $signature = 'matches:cancel-incomplete';
    protected $description = 'Cancela partidos incompletos una hora antes de empezar y reembolsa a los usuarios';

    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        parent::__construct();
        $this->walletService = $walletService;
    }

    public function handle()
    {
        Log::info('Ejecutando comando matches:cancel-incomplete');

        $now = Carbon::now();
        $oneHourFromNow = $now->copy()->addHour();

        // Obtener partidos abiertos que estén a una hora de empezar
        $matches = DailyMatch::where('status', 'open')
            ->where('schedule_date', $now->toDateString())
            ->whereBetween('start_time', [$now->toTimeString(), $oneHourFromNow->toTimeString()])
            ->with('players.user') // Cargar jugadores desde match_team_players
            ->get();

        foreach ($matches as $match) {
            // Contar jugadores reales desde la relación
            $totalPlayers = $match->players->count();
            if ($totalPlayers < $match->max_players) {
                DB::transaction(function () use ($match, $totalPlayers) {
                    // Cambiar estado a 'cancelled'
                    $match->update(['status' => 'cancelled']);
                    Log::info("Partido {$match->id} cancelado por estar incompleto", [
                        'player_count' => $totalPlayers,
                        'max_players' => $match->max_players,
                    ]);

                    // Cancelar la reserva asociada (si existe)
                    $booking = Booking::where('daily_match_id', $match->id)->first();
                    if ($booking) {
                        $booking->update(['status' => 'cancelled']);
                        Log::info("Reserva {$booking->id} cancelada");
                    }

                    // Reembolsar a los jugadores
                    $players = $match->players; // Usar la relación ajustada
                    foreach ($players as $player) {
                        $user = $player->user; // Obtener el usuario relacionado
                        $amount = $match->price; // Precio por jugador

                        // Usar WalletService para depositar el reembolso
                        $this->walletService->deposit(
                            $user,
                            $amount,
                            "Reembolso por cancelación del partido {$match->name} (ID: {$match->id})"
                        );

                        Log::info("Reembolsado $amount a usuario {$user->id} por partido {$match->id}");
                    }

                    // Notificar a los jugadores sobre la cancelación del partido
                    $this->notifyPlayersAboutMatchCancellation($match, $players);
                });
            }
        }

        $this->info('Comando ejecutado con éxito');
    }

  
    private function notifyPlayersAboutMatchCancellation(DailyMatch $match, $players)
{
    // Obtener user_ids únicos de los jugadores
    $userIds = $players->pluck('user_id')->unique()->toArray();
    Log::info('User IDs encontrados', ['userIds' => $userIds]);

    // Obtener los player_ids correspondientes desde device_tokens
    $playerIds = DeviceToken::whereIn('user_id', $userIds)
        ->pluck('player_id')
        ->toArray();

    Log::info('Player IDs encontrados', ['playerIds' => $playerIds]);

    if (!empty($playerIds)) {
        $message = "El partido {$match->name} del {$match->schedule_date} a las {$match->start_time} fue cancelado por no completarse. Se ha reembolsado el monto a tu monedero.";
        $title = "Partido Cancelado";

        // Enviar notificación a OneSignal
        $notificationController = app(NotificationController::class);
        $response = $notificationController->sendOneSignalNotification(
            $playerIds,
            $message,
            $title,
            ['type' => 'match_cancellation', 'match_id' => $match->id]
        );

        // Guardar registro en la tabla notifications
        Notification::create([
            'title' => $title,
            'message' => $message,
            'player_ids' => json_encode($playerIds),
            'read' => false,
        ]);

        Log::info('Notificación enviada a jugadores por cancelación', [
            'match_id' => $match->id,
            'response' => $response,
        ]);
    } else {
        Log::warning('No se encontraron player_ids válidos para enviar notificación', [
            'match_id' => $match->id,
            'user_ids' => $userIds,
        ]);
    }
}

}