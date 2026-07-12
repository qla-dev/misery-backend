@extends('layouts.cms')
@section('title','Stacks')
@section('content')
<div class="toolbar"><div><h1>Stacks</h1><p class="hint">Normal and Spicy are seeded automatically.</p></div></div>
<form class="panel toolbar" method="post" action="{{ route('cms.stacks.store') }}">@csrf<input name="name" required placeholder="New stack name"><button>Add stack</button></form>
<div class="panel table-wrap" style="margin-top:16px"><table><thead><tr><th>Name</th><th>Slug</th><th>Cards</th><th></th></tr></thead><tbody>
@foreach($stacks as $stack)<tr><td><b>{{ $stack->name }}</b></td><td>{{ $stack->slug }}</td><td>{{ $stack->cards_count }}</td><td>@if(!$stack->cards_count)<form method="post" action="{{ route('cms.stacks.destroy',$stack) }}">@csrf @method('DELETE')<button class="danger">Delete</button></form>@endif</td></tr>@endforeach
</tbody></table></div>
@endsection
