<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EquipoPartidoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'field' => [
                'id' => $this->field->id,
                'name' => $this->field->name,
            ],
            'schedule_date' => $this->schedule_date->format('Y-m-d'),
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'price' => $this->price,
            'player_count' => $this->player_count,
            'max_players' => $this->max_players,
            'available_spots' => $this->available_spots,
            'status' => $this->status,
            'players' => PlayerResource::collection($this->whenLoaded('players')),
            'is_full' => $this->player_count >= $this->max_players,
            'availability_status' => $this->getAvailabilityStatus()
        ];
    }

    private function getAvailabilityStatus()
    {
        if ($this->player_count >= $this->max_players) {
            return 'Completo';
        }
        $spotsLeft = $this->max_players - $this->player_count;
        if ($spotsLeft <= 2) {
            return '¡Últimos lugares!';
        }
        return 'Disponible';
    }
}