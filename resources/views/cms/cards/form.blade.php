@extends('layouts.cms')
@section('title',$card->exists?'Edit card':'New card')
@section('content')
@php($image = old('image',$card->image) && old('image',$card->image) !== '0' ? (str_starts_with(old('image',$card->image),'http') ? old('image',$card->image) : url('/card-images/'.preg_replace('#^storage/#','',old('image',$card->image)))) : null)
<div class="toolbar"><div><h1>{{ $card->exists?'Edit card':'New card' }}</h1><p class="hint">Artwork uses the image first and the native dummy illustration as fallback.</p></div><a class="btn secondary" href="{{ route('cms.cards.index') }}">Back</a></div>
<div class="form-grid"><form class="panel" method="post" enctype="multipart/form-data" action="{{ $card->exists?route('cms.cards.update',$card):route('cms.cards.store') }}">@csrf @if($card->exists)@method('PUT')@endif
<div class="field"><label>Title / situation</label><input name="title" required value="{{ old('title',$card->title) }}"></div>
<div class="field"><label>Description</label><textarea name="subtitle" rows="5">{{ old('subtitle',$card->subtitle) }}</textarea></div>
<div class="form-grid"><div class="field"><label>Misery score</label><input name="score" type="number" min="0" max="100" step="0.1" required value="{{ old('score',$card->score ?? 0) }}"></div>
<div class="field"><label>Stack</label><select name="stack_id" required>@foreach($stacks as $stack)<option value="{{ $stack->id }}" @selected(old('stack_id',$card->stack_id)==$stack->id)>{{ $stack->name }}</option>@endforeach</select></div></div>
<div class="field"><label>Image URL or storage path</label><input id="imagePath" name="image" value="{{ old('image',$card->image) }}" placeholder="cards/uploads/example.png or https://..."></div>
<div class="field"><label>Upload PNG/JPG/WebP</label><input id="imageUpload" name="image_upload" type="file" accept="image/png,image/jpeg,image/webp"><div class="hint">Maximum 8 MB. Upload replaces the current managed image.</div></div>
<button type="submit">Save card</button></form>
<div><div class="preview">@if($image)<img id="preview" src="{{ $image }}" alt="Card artwork">@else<div id="fallback" class="placeholder">⚡<div class="hint">Native fallback illustration</div></div><img id="preview" style="display:none" alt="Card artwork">@endif</div>
@if($card->exists)<form class="panel" id="artwork-generation-form" style="margin-top:16px" method="post" action="{{ route('cms.cards.generate',$card) }}">@csrf
<h3>AI artwork</h3><p class="hint">Generates a transparent 1024×1024 PNG using only white and primary amber. The file is copied into backend storage and assigned to this card.</p><button type="submit">Generate artwork</button></form>@else<div class="panel" style="margin-top:16px"><p class="hint">Save the card first to enable artwork generation.</p></div>@endif
@if(session('generated_prompt'))<details class="panel" style="margin-top:16px"><summary>Last generation prompt</summary><pre style="white-space:pre-wrap">{{ session('generated_prompt') }}</pre></details>@endif
</div></div>
@endsection
@push('scripts')<script>
const upload=document.getElementById('imageUpload'),path=document.getElementById('imagePath'),preview=document.getElementById('preview'),fallback=document.getElementById('fallback');
const generationForm=document.getElementById('artwork-generation-form');
function show(src){if(!src)return;preview.src=src;preview.style.display='block';if(fallback)fallback.style.display='none'}
upload?.addEventListener('change',()=>{const file=upload.files?.[0];if(file)show(URL.createObjectURL(file))});
path?.addEventListener('input',()=>{if(/^https?:\/\//.test(path.value))show(path.value)});
generationForm?.addEventListener('submit',()=>{
    console.info('[CMS artwork] Generate clicked',{
        cardId:{{ Illuminate\Support\Js::from($card->id) }},
        cardTitle:{{ Illuminate\Support\Js::from($card->title) }},
        endpoint:generationForm.action,
        clickedAt:new Date().toISOString(),
    });
    const button=generationForm.querySelector('button');
    button.disabled=true;
    button.textContent='Generating…';
});
</script>@endpush
