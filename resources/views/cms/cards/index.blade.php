@extends('layouts.cms')
@section('title','Cards')
@section('content')
<div class="toolbar"><div><h1>Cards</h1><p class="hint">Edit scores, stacks and generated artwork.</p></div><a class="btn" href="{{ route('cms.cards.create') }}">+ New card</a></div>
<form class="toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search title or description"><button>Search</button></form>
<div class="panel table-wrap"><table><thead><tr><th>Art</th><th>Card</th><th>Score</th><th>Stack</th><th></th></tr></thead><tbody>
@forelse($cards as $card)
@php($image = $card->image && $card->image !== '0' ? (str_starts_with($card->image,'http') ? $card->image : url('/card-images/'.preg_replace('#^storage/#','',$card->image))) : null)
<tr><td>@if($image)<img class="thumb" src="{{ $image }}" alt="">@else<div class="thumb placeholder">⚡</div>@endif</td>
<td><b>{{ $card->title }}</b><div class="hint">{{ Str::limit($card->subtitle,70) }}</div></td><td>{{ number_format($card->score,1) }}</td><td><span class="badge">{{ $card->stack?->name ?? $card->deck }}</span></td>
<td><div class="actions"><a class="btn secondary" href="{{ route('cms.cards.edit',$card) }}">Open</a><form method="post" action="{{ route('cms.cards.destroy',$card) }}" onsubmit="return confirm('Delete this card?')">@csrf @method('DELETE')<button class="danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="5">No cards found.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $cards->links() }}</div>
@endsection
