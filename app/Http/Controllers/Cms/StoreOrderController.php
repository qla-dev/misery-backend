<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = StoreOrder::query()
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('cms.orders.index', ['orders' => $orders, 'statuses' => StoreOrder::STATUSES]);
    }

    public function update(Request $request, StoreOrder $order)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(StoreOrder::STATUSES))]]);
        $order->update($data);

        return back()->with('success', 'Order status updated.');
    }
}
