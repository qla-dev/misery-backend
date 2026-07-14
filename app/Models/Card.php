<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Card extends Model { protected $fillable=['title','title_bs','subtitle','subtitle_bs','score','status','image','svg_img','deck','stack_id']; protected $casts=['score'=>'float','status'=>'boolean']; public function stack(){return $this->belongsTo(Stack::class);} }
