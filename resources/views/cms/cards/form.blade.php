@extends('layouts.cms')
@section('title',$card->exists?'Edit card':'New card')
@section('content')
@php($image = old('image',$card->image) && old('image',$card->image) !== '0' ? (str_starts_with(old('image',$card->image),'http') ? old('image',$card->image) : url('/card-images/'.preg_replace('#^storage/#','',old('image',$card->image)))) : null)
@php($svgImage = $card->svg_img ? url('/card-images/'.preg_replace('#^storage/#','',$card->svg_img)) : null)
@php($cropArtwork = session('crop_generated_artwork'))
@php($showCropper = is_array($cropArtwork) && (int) data_get($cropArtwork, 'card_id') === (int) $card->id && filled(data_get($cropArtwork, 'path')))
@php($canCropArtwork = $card->exists && filled($image))
@php($canEnhanceArtwork = $card->exists && filled($card->image) && $card->image !== '0' && !Illuminate\Support\Str::startsWith($card->image, ['http://', 'https://']))
@php($cropImage = $showCropper ? url('/card-images/'.preg_replace('#^storage/#','',data_get($cropArtwork, 'path'))) : $image)
<div class="toolbar"><div><h1>{{ $card->exists?'Edit card':'New card' }}</h1><p class="hint">Artwork uses the image first and the native dummy illustration as fallback.</p></div><a class="btn secondary" href="{{ $cardsReturnUrl ?? route('cms.cards.index') }}">Back</a></div>
<div class="form-grid"><form class="panel" method="post" enctype="multipart/form-data" action="{{ $card->exists?route('cms.cards.update',['card'=>$card,'return'=>$cardsReturnUrl]):route('cms.cards.store') }}">@csrf @if($card->exists)@method('PUT')@endif
<div class="field"><label>Title / situation</label><input id="cardTitleEn" name="title" required value="{{ old('title',$card->title) }}"></div>
<div class="field"><label>Description</label><textarea id="cardSubtitleEn" name="subtitle" rows="5">{{ old('subtitle',$card->subtitle) }}</textarea></div>
<div class="field"><label>Naslov (bosanski)</label><input id="cardTitleBs" name="title_bs" value="{{ old('title_bs',$card->title_bs) }}"></div>
<div class="field"><label>Opis (bosanski)</label><textarea id="cardSubtitleBs" name="subtitle_bs" rows="5">{{ old('subtitle_bs',$card->subtitle_bs) }}</textarea></div>
@if($card->exists)<div class="field" style="background:#101922;border:1px solid #24445c;border-radius:10px;padding:13px"><button id="translateToBosnian" class="secondary" type="button" style="width:100%">AI PREVOD NA BOSANSKI</button><p class="hint" style="margin:9px 0 0"><strong style="color:#facc15">Isključivo bosanski jezik</strong> — ne hrvatski ili srpski. AI dobija posebnu uputu da provjeri gramatiku, ijekavicu i afrikate č, ć, dž i đ. Prevod popunjava polja iznad; pregledajte ga i kliknite “Save card”.</p><p id="translationStatus" class="hint" style="margin:7px 0 0"></p></div>@endif
<input type="hidden" name="status" value="0"><label style="align-items:center;display:flex;gap:10px;margin-bottom:18px"><input type="checkbox" name="status" value="1" style="width:auto" @checked((bool) old('status',$card->exists ? $card->status : true))> Approved and available in games</label>
<div class="form-grid"><div class="field"><label>Misery score</label><input name="score" type="number" min="0" max="100" step="0.01" required value="{{ old('score',$card->score ?? 0) }}"></div>
<div class="field"><label>Stack</label><select name="stack_id" required>@foreach($stacks as $stack)<option value="{{ $stack->id }}" @selected(old('stack_id',$card->stack_id)==$stack->id)>{{ $stack->name }}</option>@endforeach</select></div></div>
<div class="field"><label>Image URL or storage path</label><input id="imagePath" name="image" value="{{ old('image',$card->image) }}" placeholder="cards/uploads/example.png or https://..."></div>
<div class="field"><label>Upload PNG/JPG/WebP</label><input id="imageUpload" name="image_upload" type="file" accept="image/png,image/jpeg,image/webp"><div class="hint">Maximum 8 MB. Upload replaces the current managed image.</div></div>
<button type="submit">Save card</button></form>
<div><section class="panel"><h3 style="margin-top:0">JPEG illustration</h3><p class="hint">Current raster artwork used by the native game. Click the image to zoom.</p><div class="preview" style="margin-top:14px">@if($image)<img id="preview" src="{{ $image }}" alt="JPEG card artwork" data-zoom-artwork>@else<div id="fallback" class="placeholder">⚡<div class="hint">Native fallback illustration</div></div><img id="preview" style="display:none" alt="JPEG card artwork" data-zoom-artwork>@endif</div>
@if($card->exists)<form id="artwork-generation-form" style="margin-top:16px" method="post" action="{{ route('cms.cards.generate',['card'=>$card,'return'=>$cardsReturnUrl]) }}">@csrf
<p class="hint">Generates a high-quality square JPEG with a solid black background and a strict black, white, and amber palette.</p><button type="submit">Generate JPEG artwork</button></form>@else<p class="hint" style="margin-top:16px">Save the card first to enable artwork generation.</p>@endif
@if($canCropArtwork)<button id="openArtworkCropper" class="secondary" type="button" style="margin-top:10px;width:100%">Crop current artwork</button>@endif
@if($canEnhanceArtwork)<form method="post" action="{{ route('cms.cards.enhance-artwork',['card'=>$card,'return'=>$cardsReturnUrl]) }}" style="margin-top:10px">@csrf<button class="secondary" type="submit" style="width:100%">Enhance with Gemini</button><p class="hint" style="margin:8px 0 0">Uses OPENROUTER_IMAGE_MODEL to restore pixelated artwork, then optimizes the JPEG below 100 KB.</p></form>@endif</section>
<section class="panel" style="margin-top:16px"><h3 style="margin-top:0">SVG code illustration</h3><p class="hint">Experimental CMS-only vector artwork generated as sanitized SVG code by the Gemini text model.</p><div class="preview" style="margin-top:14px">@if($svgImage)<img src="{{ $svgImage }}" alt="Generated SVG card artwork" data-zoom-artwork>@else<div class="placeholder">&lt;svg&gt;<div class="hint">No generated SVG illustration</div></div>@endif</div>
@if($card->exists)<form id="svg-generation-form" style="margin-top:16px" method="post" action="{{ route('cms.cards.generate-svg',['card'=>$card,'return'=>$cardsReturnUrl]) }}">@csrf<button type="submit">Generate SVG with Gemini</button></form>@else<p class="hint" style="margin-top:16px">Save the card first to enable SVG generation.</p>@endif</section>
@if(session('generated_prompt'))<details class="panel" style="margin-top:16px"><summary>Last generation prompt</summary><pre style="white-space:pre-wrap">{{ session('generated_prompt') }}</pre></details>@endif
@if(session('generated_svg_prompt'))<details class="panel" style="margin-top:16px"><summary>Last SVG generation prompt</summary><pre style="white-space:pre-wrap">{{ session('generated_svg_prompt') }}</pre></details>@endif
</div></div>
@if($canCropArtwork || $showCropper)
<style>
body.cropper-open{overflow:hidden}.generated-cropper{align-items:stretch;background:rgba(0,0,0,.96);display:flex;inset:0;position:fixed;z-index:1000}.generated-cropper.hidden{display:none}.generated-cropper__layout{display:grid;gap:24px;grid-template-columns:minmax(0,1fr) 290px;height:100%;padding:24px;width:100%}.generated-cropper__stage{align-items:center;display:flex;justify-content:center;min-height:0;overflow:hidden}.generated-cropper__frame{aspect-ratio:1;background:#000;border:2px solid var(--primary);box-shadow:0 0 35px rgba(250,204,21,.18);cursor:grab;max-height:calc(100vh - 48px);max-width:calc(100vh - 48px);position:relative;width:min(100%,calc(100vh - 48px))}.generated-cropper__frame.dragging{cursor:grabbing}.generated-cropper canvas{display:block;height:100%;touch-action:none;width:100%}.generated-cropper__panel{align-self:center;background:#171717;border:1px solid #333;border-radius:16px;padding:22px}.generated-cropper__panel h2{color:var(--primary);font-size:24px;margin:0 0 8px}.generated-cropper__panel p{color:#aaa;font-size:13px;line-height:1.5;margin:0 0 24px}.generated-cropper__panel input[type=range]{accent-color:var(--primary);padding:0}.generated-cropper__zoom{align-items:center;display:flex;gap:10px;margin:10px 0 22px}.generated-cropper__zoom output{font-variant-numeric:tabular-nums;min-width:46px;text-align:right}.generated-cropper__actions{display:grid;gap:10px}.generated-cropper__actions button{width:100%}.generated-cropper__actions .secondary{background:#292929;color:#eee}@media(max-width:760px){.generated-cropper__layout{grid-template-columns:1fr;grid-template-rows:minmax(0,1fr) auto;padding:14px}.generated-cropper__frame{max-height:calc(100vh - 245px);max-width:calc(100vh - 245px);width:min(100%,calc(100vh - 245px))}.generated-cropper__panel{padding:14px}.generated-cropper__panel p{margin-bottom:12px}.generated-cropper__zoom{margin-bottom:12px}.generated-cropper__actions{grid-template-columns:1fr 1fr 1fr}}
</style>
<div id="generatedCropper" class="generated-cropper {{ $showCropper ? '' : 'hidden' }}" role="dialog" aria-modal="true" aria-labelledby="cropperTitle">
<div class="generated-cropper__layout"><div class="generated-cropper__stage"><div id="cropFrame" class="generated-cropper__frame"><canvas id="cropCanvas" width="1024" height="1024"></canvas></div></div>
<aside class="generated-cropper__panel"><h2 id="cropperTitle">Crop artwork</h2><p>Drag the image to reposition it. Zoom in or back out to fit the complete square.</p><label for="cropZoom">Zoom</label><div class="generated-cropper__zoom"><input id="cropZoom" type="range" min="1" max="3" step="0.01" value="1"><output id="cropZoomValue">100%</output></div>
<form id="generatedCropForm" method="post" action="{{ route('cms.cards.crop-generated', ['card'=>$card,'return'=>$cardsReturnUrl]) }}">@csrf<input id="cropData" type="hidden" name="crop_data"><input type="hidden" name="generation_id" value="{{ data_get($cropArtwork, 'generation_id') }}"><div class="generated-cropper__actions"><button id="saveCrop" type="submit">Save square crop</button><button id="resetCrop" class="secondary" type="button">Reset</button><button id="closeCropper" class="secondary" type="button">Keep original</button></div></form></aside></div>
</div>
@endif
@endsection
@push('scripts')<script>
const upload=document.getElementById('imageUpload'),path=document.getElementById('imagePath'),preview=document.getElementById('preview'),fallback=document.getElementById('fallback');
const generationForm=document.getElementById('artwork-generation-form');
const svgGenerationForm=document.getElementById('svg-generation-form');
const translationButton=document.getElementById('translateToBosnian'),translationStatus=document.getElementById('translationStatus');
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
@if($card->exists)
translationButton?.addEventListener('click',async()=>{
    const title=document.getElementById('cardTitleEn').value.trim(),subtitle=document.getElementById('cardSubtitleEn').value.trim();
    if(!title){alert('Enter the English title first.');return}
    translationButton.disabled=true;translationButton.textContent='PREVODIM NA BOSANSKI…';translationStatus.textContent='AI provjerava bosanski standard, gramatiku i afrikate…';
    try{
        const response=await fetch({{ Illuminate\Support\Js::from(route('cms.cards.translate-bs',$card)) }},{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({title,subtitle})});
        const payload=await response.json();if(!response.ok)throw new Error(payload.message||'AI translation failed.');
        document.getElementById('cardTitleBs').value=payload.title_bs||'';document.getElementById('cardSubtitleBs').value=payload.subtitle_bs||'';
        translationStatus.textContent=`Bosanski prevod je popunjen (${payload.provider}). Pregledajte i sačuvajte karticu.`;
    }catch(error){translationStatus.textContent='Prevod nije uspio.';alert(error.message||'AI translation failed.')}finally{translationButton.disabled=false;translationButton.textContent='AI PREVOD NA BOSANSKI'}
});
@endif
@if($canCropArtwork || $showCropper)
(()=>{
    const modal=document.getElementById('generatedCropper'),frame=document.getElementById('cropFrame'),canvas=document.getElementById('cropCanvas');
    const openButton=document.getElementById('openArtworkCropper');
    const zoomInput=document.getElementById('cropZoom'),zoomValue=document.getElementById('cropZoomValue'),form=document.getElementById('generatedCropForm');
    const cropData=document.getElementById('cropData'),saveButton=document.getElementById('saveCrop'),context=canvas.getContext('2d');
    const image=new Image();
    let zoom=1,offsetX=0,offsetY=0,dragging=false,lastX=0,lastY=0,ready=false;
    if(!modal.classList.contains('hidden'))document.body.classList.add('cropper-open');
    function dimensions(){const base=Math.min(canvas.width/image.naturalWidth,canvas.height/image.naturalHeight);return{width:image.naturalWidth*base*zoom,height:image.naturalHeight*base*zoom}}
    function clampOffset(){const size=dimensions(),xLimit=Math.max(0,size.width-canvas.width)/2,yLimit=Math.max(0,size.height-canvas.height)/2;offsetX=Math.max(-xLimit,Math.min(xLimit,offsetX));offsetY=Math.max(-yLimit,Math.min(yLimit,offsetY))}
    function draw(){if(!ready)return;clampOffset();const size=dimensions();context.fillStyle='#000';context.fillRect(0,0,canvas.width,canvas.height);context.imageSmoothingEnabled=true;context.imageSmoothingQuality='high';context.drawImage(image,(canvas.width-size.width)/2+offsetX,(canvas.height-size.height)/2+offsetY,size.width,size.height)}
    function setZoom(next){const previous=zoom;zoom=Math.max(1,Math.min(3,next));if(previous>0){offsetX*=zoom/previous;offsetY*=zoom/previous}zoomInput.value=String(zoom);zoomValue.value=`${Math.round(zoom*100)}%`;draw()}
    function open(){modal.classList.remove('hidden');document.body.classList.add('cropper-open');draw()}
    function close(){modal.classList.add('hidden');document.body.classList.remove('cropper-open')}
    image.addEventListener('load',()=>{ready=true;draw()});
    image.addEventListener('error',()=>{alert('The generated artwork could not be loaded.');close()});
    image.src={{ Illuminate\Support\Js::from($cropImage) }};
    openButton?.addEventListener('click',open);
    zoomInput.addEventListener('input',()=>setZoom(Number(zoomInput.value)));
    frame.addEventListener('wheel',event=>{event.preventDefault();setZoom(zoom+(event.deltaY < 0 ? .08 : -.08))},{passive:false});
    canvas.addEventListener('pointerdown',event=>{if(!ready)return;dragging=true;lastX=event.clientX;lastY=event.clientY;canvas.setPointerCapture(event.pointerId);frame.classList.add('dragging')});
    canvas.addEventListener('pointermove',event=>{if(!dragging)return;const rect=canvas.getBoundingClientRect();offsetX+=(event.clientX-lastX)*(canvas.width/rect.width);offsetY+=(event.clientY-lastY)*(canvas.height/rect.height);lastX=event.clientX;lastY=event.clientY;draw()});
    const stopDrag=()=>{dragging=false;frame.classList.remove('dragging')};
    canvas.addEventListener('pointerup',stopDrag);canvas.addEventListener('pointercancel',stopDrag);
    document.getElementById('resetCrop').addEventListener('click',()=>{offsetX=0;offsetY=0;setZoom(1)});
    document.getElementById('closeCropper').addEventListener('click',close);
    form.addEventListener('submit',event=>{if(!ready){event.preventDefault();return}draw();cropData.value=canvas.toDataURL('image/jpeg',.94);saveButton.disabled=true;saveButton.textContent='Saving crop...'});
})();
@endif
</script>@endpush
