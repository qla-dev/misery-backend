<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Card extends Model { protected $fillable=['title','subtitle','score','image','deck']; protected $casts=['score'=>'float']; }
