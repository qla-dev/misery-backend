<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreOrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1', 'max:4'],
            'language' => ['nullable', Rule::in(['bs', 'en'])],
        ]);
        $price = round((float) config('shop.game_price'), 2);
        $order = StoreOrder::create($data + [
            'unit_price' => $price,
            'total' => round($price * $data['quantity'], 2),
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => ['id' => $order->id, 'status' => $order->status],
            'message' => 'Order received.',
        ], 201);
    }
}
