@extends('layouts.cms')
@section('title','Home')
@section('content')
<div class="toolbar"><div><h1>CMS Home</h1><p class="hint">Manage game content without touching the database.</p></div><a class="btn" href="{{ route('cms.cards.create') }}">+ New card</a></div>
<div class="grid stats">
<div class="panel stat"><b>{{ \App\Models\Card::count() }}</b><span>Cards</span></div>
<div class="panel stat"><b>{{ \App\Models\Stack::count() }}</b><span>Stacks</span></div>
<div class="panel stat"><b>{{ \App\Models\Card::whereNotNull('image')->where('image','!=','0')->count() }}</b><span>Cards with artwork</span></div>
<div class="panel stat"><b>{{ \App\Models\Card::where(fn ($query) => $query->whereNull('image')->orWhere('image','0'))->count() }}</b><span>Cards awaiting artwork</span></div>
<a class="panel stat" href="{{ route('cms.generator.index') }}"><b>AI</b><span>Generate card content</span></a>
</div>
@endsection
