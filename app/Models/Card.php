<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Card extends Model { protected $fillable=['title','subtitle','score','image','svg_img','deck','stack_id']; protected $casts=['score'=>'float']; public function stack(){return $this->belongsTo(Stack::class);} }
