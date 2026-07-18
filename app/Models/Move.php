<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Move extends Model { protected $fillable=['game_id','player_id','card_id','correct','is_steal']; protected $casts=['correct'=>'boolean','is_steal'=>'boolean']; public function player(){return $this->belongsTo(User::class,'player_id');} public function card(){return $this->belongsTo(Card::class);} }
