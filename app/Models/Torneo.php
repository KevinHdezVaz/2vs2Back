<?php
namespace App\Models;
use App\Models\Equipo;
use Illuminate\Database\Eloquent\Model;

class Torneo extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'maximo_equipos',
        'minimo_equipos',
        'cuota_inscripcion',
        'premio',
        'formato',
        'reglas',
        'imagenesTorneo'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'reglas' => 'array',
        'imagenesTorneo' => 'array' 
    ];

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'torneo_equipos')
                    ->withPivot('estado', 'pago_confirmado');
    }
    

    public function partidos()
    {
        return $this->hasMany(Partido::class);
    }

    public function estadisticas()
    {
        return $this->hasMany(EstadisticaTorneo::class);
    }

    public function getEstadoColor()
{
    $colores = [
        'borrador' => 'warning',  // Amarillo
        'en_progreso' => 'info',      // Azul
        'abierto' => 'success', // Verde
        'cancelado' => 'danger',   // Rojo
        'completado' => 'warning'
    ];

    return $colores[$this->estado] ?? 'secondary'; // Gris por defecto
}

}