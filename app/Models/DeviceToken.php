<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';
    
    protected $fillable = [
        'player_id',
        'user_id'
    ];

    // Agregamos mutador
    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (int)$value;
    }

    // Definir la relación con el modelo User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}