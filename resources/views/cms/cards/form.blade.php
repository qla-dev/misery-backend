@extends('layouts.cms')
@section('title',$card->exists?'Edit card':'New card')
@section('content')
@php($image = old('image',$card->image) && old('image',$card->image) !== '0' ? (str_starts_with(old('image',$card->image),'http') ? old('image',$card->image) : url('/card-images/'.preg_replace('#^storage/#','',old('image',$card->image)))) : null)
@php($svgImage = $card->svg_img ? url('/card-images/'.preg_replace('#^storage/#','',$card->svg_img)) : null)
<div class="toolbar"><div><h1>{{ $card->exists?'Edit card':'New card' }}</h1><p class="hint">Artwork uses the image first and the native dummy illustration as fallback.</p></div><a class="btn secondary" href="{{ route('cms.cards.index') }}">Back</a></div>
<div class="form-grid"><form class="panel" method="post" enctype="multipart/form-data" action="{{ $card->exists?route('cms.cards.update',$card):route('cms.cards.store') }}">@csrf @if($card->exists)@method('PUT')@endif
<div class="field"><label>Title / situation</label><input name="title" required value="{{ old('title',$card->title) }}"></div>
<div class="field"><label>Description</label><textarea name="subtitle" rows="5">{{ old('subtitle',$card->subtitle) }}</textarea></div>
<div class="field"><label>Naslov (bosanski)</label><input name="title_bs" value="{{ old('title_bs',$card->title_bs) }}"></div>
<div class="field"><label>Opis (bosanski)</label><textarea name="subtitle_bs" rows="5">{{ old('subtitle_bs',$card->subtitle_bs) }}</textarea></div>
<input type="hidden" name="status" value="0"><label style="align-items:center;display:flex;gap:10px;margin-bottom:18px"><input type="checkbox" name="status" value="1" style="width:auto" @checked((bool) old('status',$card->exists ? $card->status : true))> Approved and available in games</label>
<div class="form-grid"><div class="field"><label>Misery score</label><input name="score" type="number" min="0" max="100" step="0.01" required value="{{ old('score',$card->score ?? 0) }}"></div>
<div class="field"><label>Stack</label><select name="stack_id" required>@foreach($stacks as $stack)<option value="{{ $stack->id }}" @selected(old('stack_id',$card->stack_id)==$stack->id)>{{ $stack->name }}</option>@endforeach</select></div></div>
<div class="field"><label>Image URL or storage path</label><input id="imagePath" name="image" value="{{ old('image',$card->image) }}" placeholder="cards/uploads/example.png or https://..."></div>
<div class="field"><label>Upload PNG/JPG/WebP</label><input id="imageUpload" name="image_upload" type="file" accept="image/png,image/jpeg,image/webp"><div class="hint">Maximum 8 MB. Upload replaces the current managed image.</div></div>
<button type="submit">Save card</button></form>
<div><section class="panel"><h3 style="margin-top:0">JPEG illustration</h3><p class="hint">Current raster artwork used by the native game.</p><div class="preview" style="margin-top:14px">@if($image)<img id="preview" src="{{ $image }}" alt="JPEG card artwork">@else<div id="fallback" class="placeholder">⚡<div class="hint">Native fallback illustration</div></div><img id="preview" style="display:none" alt="JPEG card artwork">@endif</div>
@if($card->exists)<form id="artwork-generation-form" style="margin-top:16px" method="post" action="{{ route('cms.cards.generate',$card) }}">@csrf
<p class="hint">Generates a high-quality square JPEG with a solid black background and a strict black, white, and amber palette.</p><button type="submit">Generate JPEG artwork</button></form>@else<p class="hint" style="margin-top:16px">Save the card first to enable artwork generation.</p>@endif</section>
<section class="panel" style="margin-top:16px"><h3 style="margin-top:0">SVG code illustration</h3><p class="hint">Experimental CMS-only vector artwork generated as sanitized SVG code by the Gemini text model.</p><div class="preview" style="margin-top:14px">@if($svgImage)<img src="{{ $svgImage }}" alt="Generated SVG card artwork">@else<div class="placeholder">&lt;svg&gt;<div class="hint">No generated SVG illustration</div></div>@endif</div>
@if($card->exists)<form id="svg-generation-form" style="margin-top:16px" method="post" action="{{ route('cms.cards.generate-svg',$card) }}">@csrf<button type="submit">Generate SVG with Gemini</button></form>@else<p class="hint" style="margin-top:16px">Save the card first to enable SVG generation.</p>@endif</section>
@if(session('generated_prompt'))<details class="panel" style="margin-top:16px"><summary>Last generation prompt</summary><pre style="white-space:pre-wrap">{{ session('generated_prompt') }}</pre></details>@endif
@if(session('generated_svg_prompt'))<details class="panel" style="margin-top:16px"><summary>Last SVG generation prompt</summary><pre style="white-space:pre-wrap">{{ session('generated_svg_prompt') }}</pre></details>@endif
</div></div>
@endsection
@push('scripts')<script>
const upload=document.getElementById('imageUpload'),path=document.getElementById('imagePath'),preview=document.getElementById('preview'),fallback=document.getElementById('fallback');
const generationForm=document.getElementById('artwork-generation-form');
const svgGenerationForm=document.getElementById('svg-generation-form');
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
svgGenerationForm?.addEventListener('submit',()=>{
    console.info('[CMS SVG] Generate clicked',{
        cardId:{{ Illuminate\Support\Js::from($card->id) }},
        cardTitle:{{ Illuminate\Support\Js::from($card->title) }},
        endpoint:svgGenerationForm.action,
        clickedAt:new Date().toISOString(),
    });
    const button=svgGenerationForm.querySelector('button');
    button.disabled=true;
    button.textContent='Generating SVG…';
});
</script>@endpush
