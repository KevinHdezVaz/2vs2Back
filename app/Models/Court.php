<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    protected $fillable = [
        'session_id',
        'court_name',
        'court_number',
        'status' // available, occupied
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function getCurrentGame()
    {
        return $this->games()
            ->where('status', 'active')
            ->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}