<?php

namespace App\Models;

use App\Models\MealLog; 
use App\Models\MealPlan;
use App\Models\StreakLog;
use App\Models\UserProfile;
use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Los atributos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone', // <-- Agrega esta línea

        'firebase_uid', // Añadido para Google Sign-In
        'trial_ends_at',
        'message_count', // <-- AÑADE ESTA LÍNEA

        'subscription_status',
        'subscription_ends_at', // <-- AÑADE ESTA LÍNEA

        'applied_affiliate_code', // <-- AÑADE ESTA LÍNEA

    ];

    /**
     * Los atributos que deben ocultarse en las respuestas JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'trial_ends_at' => 'datetime',      // <-- Asegúrate que esta línea exista
        'subscription_ends_at' => 'datetime', // <-- AÑADE ESTA LÍNEA
    ];
    /**
     * Define la relación: un Usuario tiene un Perfil.
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }


    public function hasTrialExpired()
    {
        // La prueba ha expirado si el estado es 'trial' y la fecha de finalización ya pasó.
        return $this->subscription_status === 'trial' && Carbon::now()->isAfter($this->trial_ends_at);
    }

    
    public function streakLogs()
    {
        return $this->hasMany(StreakLog::class);
    }
    
    public function mealLogs()
    {
        // Esto define una relación "Uno a Muchos" con el modelo MealLog
        return $this->hasMany(MealLog::class);
    }

    
    /**
     * Define la relación: un Usuario tiene un Plan de Comidas activo.
     */
    public function activePlan()
    {
        return $this->hasOne(MealPlan::class)->where('is_active', true);
    }


    
}