<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Game extends Model { protected $fillable=['code','owner_id','started','current_card_id']; protected $casts=['started'=>'boolean']; public function owner(){return $this->belongsTo(User::class,'owner_id');} public function members(){return $this->belongsToMany(User::class,'members')->withTimestamps();} public function moves(){return $this->hasMany(Move::class);} public function currentCard(){return $this->belongsTo(Card::class,'current_card_id');} }
