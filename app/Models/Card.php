<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Card extends Model { protected $fillable=['title','title_bs','subtitle','subtitle_bs','score','status','image','artwork_enhanced','svg_img','deck','stack_id']; protected $casts=['score'=>'float','status'=>'boolean','artwork_enhanced'=>'boolean']; public function stack(){return $this->belongsTo(Stack::class);} }
