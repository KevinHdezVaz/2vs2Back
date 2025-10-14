<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TorneoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'estado' => $this->estado,
            'formato' => $this->formato,
            'maximo_equipos' => $this->maximo_equipos,
            'minimo_equipos' => $this->minimo_equipos,
            'cuota_inscripcion' => (float) $this->cuota_inscripcion,
            'premio' => $this->premio,
            'imagenesTorneo' => $this->imagenesTorneo ? json_decode($this->imagenesTorneo) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}