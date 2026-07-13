<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
    public const STATUSES = [
        'pending' => 'Pending confirmation',
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = ['name', 'email', 'phone', 'address', 'quantity', 'unit_price', 'total', 'status', 'language'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];
}
