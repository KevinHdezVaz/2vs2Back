<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProfile extends Model
{
    use HasFactory;

    protected $appends = ['streak_history'];


    protected $fillable = [
        'onesignal_player_id',
        'user_id', 'height', 'weight', 'age', 'sex', 'goal', 'activity_level',
        'weekly_activity', // Agrega esta línea
        'sport', 'training_frequency', 'meal_count', 'breakfast_time', 'lunch_time', 'dinner_time',
        'dietary_style', 'budget', 'cooking_habit', 'eats_out', 'disliked_foods',
        'has_allergies', 'allergies', 'has_medical_condition', 'medical_condition',
        'communication_style', 'motivation_style', 'preferred_name', 'things_to_avoid',
        'name', 'plan_setup_complete', 'diet_difficulties', 'diet_motivations',
        'pais', 'racha_actual', 'ultima_fecha_racha'
    ];

   
    protected $casts = [
        'sport' => 'array',
        'diet_difficulties' => 'array',
        'diet_motivations' => 'array',
        'has_allergies' => 'boolean',
        'has_medical_condition' => 'boolean',
        'plan_setup_complete' => 'boolean',
        'ultima_fecha_racha' => 'date', // Laravel convertirá este campo a un objeto Carbon automáticamente
    ];

    public function streakLogs()
    {
        return $this->hasMany(StreakLog::class, 'user_id', 'user_id');
    }

    public function getStreakHistoryAttribute()
    {
        // Carga la relación 'streakLogs', y de cada registro, extrae solo la fecha.
        // El resultado es un array de strings con las fechas, ej: ["2025-06-25", "2025-06-26"]
        // ¡Justo lo que Flutter necesita!
        return $this->streakLogs()->pluck('completed_at');
    }

    
public function actualizarRacha()
{
    // Usamos Carbon::now() sin argumentos. Automáticamente usará la zona horaria
    // que configuraste en config/app.php ('America/Mexico_City').
    $hoy = Carbon::now();
    $ultimaFecha = $this->ultima_fecha_racha ? Carbon::parse($this->ultima_fecha_racha) : null;

    // Si ya se completó hoy, no hacer nada.
    if ($ultimaFecha && $ultimaFecha->isSameDay($hoy)) {
        return;
    }

    DB::transaction(function () use ($hoy, $ultimaFecha) {
        // Calculamos la diferencia de días desde la última vez.
        $diffInDays = $ultimaFecha ? $hoy->diffInDays($ultimaFecha) : 999;

        // Si la diferencia es de 1 día (ayer), la racha continúa normalmente.
        if ($diffInDays == 1) {
            $this->racha_actual++;
        } else {
            // Si es cualquier otro día, la racha se rompió y empieza de nuevo en 1.
            $this->racha_actual = 1;
        }

        $this->ultima_fecha_racha = $hoy->toDateString(); // Guardará la fecha correcta (ej: 2025-06-25)
        $this->save();

        // Registrar el día completado en el historial.
        DB::table('streak_logs')->updateOrInsert(
            [
                'user_id' => $this->user_id,
                'completed_at' => $hoy->toDateString(), // También guardará la fecha correcta
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    });
}


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}