@extends('layouts.cms')
@section('title','Store orders')
@section('content')
<div class="toolbar"><div><h1>Store orders</h1><p class="hint">Cash-on-delivery requests from the public landing page.</p></div></div>
<form class="panel" method="get" style="align-items:end;display:grid;gap:12px;grid-template-columns:1fr auto;margin-bottom:18px"><div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><button>Filter</button></form>
<div class="panel table-wrap"><table><thead><tr><th>#</th><th>Customer</th><th>Contact</th><th>Delivery address</th><th>Order</th><th>Placed</th><th>Status</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->id }}</td><td><strong>{{ $order->name }}</strong><br><span class="hint">{{ strtoupper($order->language) }}</span></td><td>{{ $order->email }}<br>{{ $order->phone }}</td><td style="min-width:220px">{{ $order->address }}</td><td>{{ $order->quantity }} × {{ $order->unit_price }} KM<br><strong>{{ $order->total }} KM</strong></td><td>{{ $order->created_at->format('d.m.Y H:i') }}</td><td><form method="post" action="{{ route('cms.orders.update',$order) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()">@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($order->status===$value)>{{ $label }}</option>@endforeach</select></form></td></tr>@empty<tr><td colspan="7" class="hint">No orders yet.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $orders->links() }}</div>
@endsection
