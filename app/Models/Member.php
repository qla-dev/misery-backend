<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Member extends Model { protected $fillable=['game_id','user_id']; public function game(){return $this->belongsTo(Game::class);} public function user(){return $this->belongsTo(User::class);} }
