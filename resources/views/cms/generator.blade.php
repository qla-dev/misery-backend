@extends('layouts.cms')
@section('title','Card Generator')
@section('content')
<style>
.choice-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.choice{background:#101011;border:2px solid #333;border-radius:12px;cursor:pointer;padding:15px;transition:.15s}.choice:hover{border-color:#737373}.choice:has(input:checked){background:#2b2507;border-color:var(--primary);color:var(--primary)}.choice input{height:0;opacity:0;position:absolute;width:0}.choice strong{display:block;font-size:15px}.choice span{color:#aaa;display:block;font-size:12px;margin-top:4px}.generate-note{background:#211d08;border:1px solid #665b16;border-radius:12px;color:#fde68a;margin-bottom:20px;padding:14px}
</style>
<div class="toolbar"><div><h1>Generate card content</h1><p class="hint">Gemini creates situation titles, descriptions, and suggested two-decimal misery scores. Artwork is generated separately.</p></div><a class="btn secondary" href="{{ route('cms.cards.index') }}">View cards</a></div>
<div class="generate-note"><strong>10 unique cards will be added to the Normal deck.</strong><br>Existing card text and scores are sent as exclusion rules and scoring anchors. New cards start without artwork so you can review and generate each illustration.</div>
<form id="generate-form" class="panel" method="post" action="{{ route('cms.generator.generate') }}">
    @csrf
    <div class="field"><label>Situation theme</label><div class="choice-grid">@foreach($themes as $value => $label)<label class="choice"><input type="radio" name="theme" value="{{ $value }}" required @checked(old('theme','mixed') === $value)><strong>{{ $label }}</strong><span>Generate original {{ strtolower($label) }}</span></label>@endforeach</div></div>
    <div class="field" style="margin-top:24px"><label>Misery score range</label><div class="choice-grid">@foreach($severities as $value => $details)<label class="choice"><input type="radio" name="severity" value="{{ $value }}" required @checked(old('severity','mixed') === $value)><strong>{{ $details[0] }}</strong><span>{{ number_format($details[1],2) }}–{{ number_format($details[2],2) }}</span></label>@endforeach</div></div>
    <button id="generate-button" type="submit" style="margin-top:10px">Generate and save 10 cards</button>
</form>
@endsection
@push('scripts')
<script>document.getElementById('generate-form').addEventListener('submit',function(){const button=document.getElementById('generate-button');button.disabled=true;button.textContent='Generating card content…';});</script>
@endpush
