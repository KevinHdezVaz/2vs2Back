<?php

namespace App\Models;

use App\Models\Bono;
use App\Models\User;
use App\Models\BonoUse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserBono extends Model
{
    use HasFactory;

    protected $table = 'user_bonos';

    protected $fillable = [
        'user_id',
        'bono_id',
        'fecha_compra',
        'fecha_vencimiento',
        'codigo_referencia',
        'payment_id',
        'estado',
        'usos_disponibles',
        'usos_totales',
        'restricciones_horarias',
    ];

    protected $casts = [
        'fecha_compra' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'restricciones_horarias' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bono()
    {
        return $this->belongsTo(Bono::class);
    }

    public function usos()
    {
        return $this->hasMany(BonoUse::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo')
                    ->where('fecha_vencimiento', '>=', now());
    }
}