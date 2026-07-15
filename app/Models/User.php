<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'apple_id',
        'color',
        'pro_status',
        'pro_started_at',
        'pro_ends_at',
        'revenuecat_product_id',
        'revenuecat_entitlement_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pro_started_at' => 'datetime',
            'pro_ends_at' => 'datetime',
        ];
    }

    public function games()
    {
        return $this->belongsToMany(Game::class, 'members')->withTimestamps();
    }

    public function gameMessages()
    {
        return $this->hasMany(GameMessage::class);
    }
}
