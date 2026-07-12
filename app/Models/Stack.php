<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stack extends Model
{
    protected $fillable = ['name', 'slug'];

    public function cards() { return $this->hasMany(Card::class); }
}
