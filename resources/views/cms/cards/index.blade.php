@extends('layouts.cms')
@section('title','Cards')
@section('content')
<div class="toolbar"><div><h1>Cards</h1><p class="hint">Edit scores, stacks and generated artwork.</p></div><a class="btn" href="{{ route('cms.cards.create') }}">+ New card</a></div>
<form class="toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search title or description"><select name="status" style="max-width:180px"><option value="">All statuses</option><option value="0" @selected(request('status')==='0')>Drafts</option><option value="1" @selected(request('status')==='1')>Approved</option></select><button>Filter</button></form>
<div class="panel table-wrap"><table><thead><tr><th>Art</th><th>Card</th><th>Score</th><th>Status</th><th>Stack</th><th></th></tr></thead><tbody>
@forelse($cards as $card)
@php($image = $card->image && $card->image !== '0' ? (str_starts_with($card->image,'http') ? $card->image : url('/card-images/'.preg_replace('#^storage/#','',$card->image))) : null)
<tr><td>@if($image)<img class="thumb" src="{{ $image }}" alt="">@else<div class="thumb placeholder">⚡</div>@endif</td>
<td><b>{{ $card->title }}</b><div class="hint">{{ Str::limit($card->subtitle,70) }}</div></td><td>{{ number_format($card->score,2) }}</td><td><span class="badge" style="background:{{ $card->status?'#14532d':'#3f3f46' }}">{{ $card->status?'APPROVED':'DRAFT' }}</span></td><td><span class="badge">{{ $card->stack?->name ?? $card->deck }}</span></td>
<td><div class="actions"><form method="post" action="{{ route('cms.cards.status',$card) }}">@csrf<input type="hidden" name="status" value="{{ $card->status?0:1 }}"><button class="secondary">{{ $card->status?'Unapprove':'Approve' }}</button></form><a class="btn secondary" href="{{ route('cms.cards.edit',$card) }}">Open</a><form method="post" action="{{ route('cms.cards.destroy',$card) }}" onsubmit="return confirm('Delete this card?')">@csrf @method('DELETE')<button class="danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="5">No cards found.</td></tr>@endforelse
</tbody></table></div>{{ $cards->links('cms.pagination') }}
@endsection
