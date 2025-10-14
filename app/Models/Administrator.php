<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // <-- IMPORTANTE
use Illuminate\Notifications\Notifiable;

class Administrator extends Authenticatable // <-- DEBE DECIR "extends Authenticatable"
{
    use HasFactory, Notifiable;

    /**
     * La tabla asociada con el modelo.
     * @var string
     */
    protected $table = 'administrators'; // <-- Añade esto para estar seguros

    /**
     * Los atributos que se pueden asignar masivamente.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Los atributos que deben estar ocultos.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
}