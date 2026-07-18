<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    protected $fillable = ['game_id', 'type', 'target_user_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
