@extends('layouts.cms')
@section('title','Generate questions')
@section('content')
<style>
.choice-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}.choice{background:#101011;border:2px solid #333;border-radius:12px;cursor:pointer;padding:15px;transition:.15s}.choice:hover{border-color:#737373}.choice:has(input:checked){background:#2b2507;border-color:var(--primary);color:var(--primary)}.choice input{height:0;opacity:0;position:absolute;width:0}.choice strong{display:block;font-size:16px}.choice span{color:#aaa;display:block;font-size:12px;margin-top:4px}.generate-note{background:#211d08;border:1px solid #665b16;border-radius:12px;color:#fde68a;margin-bottom:20px;padding:14px}
</style>
<div class="toolbar"><div><h1>Generate questions with AI</h1><p class="hint">Question text and answers only — no images are generated.</p></div><a class="btn secondary" href="{{ route('cms.questions.index') }}">Back</a></div>
<div class="generate-note"><strong>Exactly 10 accepted questions will be saved automatically as drafts (status 0).</strong><br>The generator checks every existing question and the new batch for duplicates and similar wording before saving anything.</div>
<form id="generate-form" class="panel" method="post" action="{{ route('cms.questions.generate') }}">
    @csrf
    <div class="field"><label>Choose a category</label><div class="choice-grid">@foreach($categories as $value => $label)<label class="choice"><input type="radio" name="category" value="{{ $value }}" required @checked(old('category') === $value)><strong>{{ $label }}</strong><span>Generate {{ strtolower($label) }} trivia</span></label>@endforeach</div></div>
    <div class="field" style="margin-top:24px"><label>Choose one of 4 difficulty ranges</label><div class="choice-grid">@foreach($difficulties as $value => $label)<label class="choice"><input type="radio" name="difficulty" value="{{ $value }}" required @checked((string) old('difficulty') === (string) $value)><strong>{{ $value }} — {{ $label }}</strong><span>@if($value===1)Casual, widely known facts@elseif($value===2)Some subject knowledge needed@elseif($value===3)For knowledgeable players@else Expert-level but fair @endif</span></label>@endforeach</div></div>
    <button id="generate-button" type="submit" style="margin-top:10px">Generate and save 10 drafts</button>
</form>
@endsection
@push('scripts')
<script>document.getElementById('generate-form').addEventListener('submit',function(){const button=document.getElementById('generate-button');button.disabled=true;button.textContent='Generating 10 unique questions…';});</script>
@endpush
