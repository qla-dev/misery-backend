@extends('layouts.cms')
@section('title','Screenshot Maker')
@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
.shot-head h1{font:46px/1 "Bebas Neue";letter-spacing:.03em;margin:0}.shot-head p{margin:7px 0 0}.reference-panel{margin-bottom:22px}.reference-top{align-items:end;display:flex;gap:12px;justify-content:space-between}.reference-top input{max-width:560px}.reference-list{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.reference-item{background:#0b0b0c;border:1px solid #333;border-radius:10px;overflow:hidden;position:relative}.reference-item img{display:block;height:110px;object-fit:cover;width:82px}.reference-item button{background:#7f1d1d;border-radius:99px;color:#fff;font-size:11px;height:25px;padding:0;position:absolute;right:4px;top:4px;width:25px}.shot-note{background:#2a2406;border:1px solid #806d08;border-radius:10px;color:#eadf9b;font-size:12px;line-height:1.5;margin-top:12px;padding:10px}.frames{display:grid;gap:20px}.shot-frame{display:grid;gap:18px;grid-template-columns:240px minmax(0,1fr)}.shot-preview{align-items:center;background:radial-gradient(circle at 50% 30%,#665404,#181300 50%,#070707);border:1px solid #3e3713;border-radius:14px;display:flex;justify-content:center;min-height:500px;overflow:hidden;padding:14px;position:relative}.shot-preview:before{background:repeating-radial-gradient(ellipse at 50% 65%,transparent 0 28px,rgba(250,204,21,.13) 30px 47px);content:"";inset:0;position:absolute}.shot-preview img{border-radius:20px;box-shadow:0 16px 40px #000;max-height:470px;max-width:100%;position:relative}.shot-empty{color:#c5ad34;font:28px/1 "Bebas Neue";letter-spacing:.08em;position:relative;text-align:center}.shot-form h2{align-items:center;display:flex;font-size:20px;justify-content:space-between;margin:0 0 15px}.shot-form h2 b{color:#facc15;font:34px/1 "Bebas Neue"}.shot-fields{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}.shot-fields .wide{grid-column:1/-1}.shot-fields label{font-size:12px}.shot-fields textarea{min-height:70px;resize:vertical}.shot-generate{font-size:15px;margin-top:14px;padding:13px;width:100%}.shot-status{color:#aaa;font-size:12px;margin-top:10px;min-height:18px}.shot-status.error{color:#fca5a5}.shot-download{display:none;margin-top:9px;text-align:center}.shot-download.visible{display:block}@media(max-width:800px){.reference-top{align-items:stretch;flex-direction:column}.shot-frame{grid-template-columns:1fr}.shot-preview{min-height:400px}.shot-preview img{max-height:540px}}@media(max-width:550px){.shot-fields{grid-template-columns:1fr}.shot-fields .wide{grid-column:auto}}
</style>

<div class="toolbar shot-head"><div><h1>Screenshot Maker</h1><p class="hint">10 Apple Store frames · Gemini image generation · editable before every generation.</p></div><span class="badge">1290 × 2796</span></div>

<section class="panel reference-panel">
  <div class="reference-top">
    <div><h2 style="margin:0 0 5px">App Store style references</h2><p class="hint" style="margin:0">Upload the examples once. Every saved reference is automatically sent to Gemini for all 10 frames.</p></div>
    <form id="referenceForm"><input id="referenceFiles" type="file" name="references[]" accept="image/png,image/jpeg,image/webp" multiple required><button style="margin-top:8px;width:100%">Save references</button></form>
  </div>
  <div id="referenceList" class="reference-list">
    @foreach($references as $reference)
      <div class="reference-item" data-name="{{ $reference['name'] }}"><img src="{{ $reference['url'] }}" alt="App Store style reference"><button type="button" title="Delete">×</button></div>
    @endforeach
  </div>
  <div class="shot-note"><b>Locked direction:</b> every generated screenshot receives the Misery-yellow rainbow background. References control hierarchy, phone staging and commercial polish only; Gemini is instructed never to copy another app’s brand or text.</div>
</section>

<div class="frames">
@for($frame=1;$frame<=10;$frame++)
  <section class="panel shot-frame" data-frame="{{ $frame }}">
    <div class="shot-preview"><div class="shot-empty">FRAME {{ str_pad($frame,2,'0',STR_PAD_LEFT) }}<br><small style="font:12px Outfit">YELLOW RAINBOW</small></div></div>
    <form class="shot-form">
      <input type="hidden" name="frame" value="{{ $frame }}">
      <h2><span>Apple screenshot frame</span><b>{{ str_pad($frame,2,'0',STR_PAD_LEFT) }}</b></h2>
      <div class="shot-fields">
        <div class="wide"><label>Headline · edit before generation</label><input name="headline" maxlength="90" required value="{{ ['RANK THE WORST. SURVIVE THE LAUGHS.','BUILD YOUR MISERY LANE','PLAY LIVE WITH YOUR FRIENDS','PLACE IT. REVEAL IT. REGRET IT.','STEAL THEIR WORST MOMENT','EVERY BAD DAY HAS A SCORE','CREATE A ROOM IN SECONDS','THREE DECKS. ENDLESS DISASTER.','WHO KNOWS MISERY BEST?','YOUR NEXT GAME NIGHT IS DOOMED.'][$frame-1] }}"></div>
        <div class="wide"><label>Supporting text · edit before generation</label><textarea name="supporting_text" maxlength="180">{{ ['The party game where terrible situations become brilliant decisions.','Order the cards from barely bad to absolute catastrophe.','Invite the group and watch every terrible choice unfold in real time.','Guess where the disaster belongs before the hidden Misery Rate appears.','A wrong placement gives the next player one perfect chance to take it.','Compare impossible situations on one brutally simple scale.','Share one code and bring everyone into the same live game.','Choose Normal, Spicy or 18+ and set the tone for the night.','Argue, laugh and discover which friend truly understands a bad day.','Download Misery Meter and turn terrible decisions into a great night.'][$frame-1] }}</textarea></div>
        <div><label>Text position</label><select name="text_position"><option value="top" @selected($frame%2===1)>Top, above phone</option><option value="bottom" @selected($frame%2===0)>Bottom, below phone</option></select></div>
        <div><label>Phone angle</label><select name="phone_angle"><option value="front">Straight front</option><option value="left">Perspective left</option><option value="right">Perspective right</option><option value="tilted-left">Tilted left</option><option value="tilted-right">Tilted right</option><option value="close-up">Close-up crop</option></select></div>
        <div class="wide"><label>Background direction</label><input name="background" maxlength="300" value="{{ $frame%3===0?'Black stage with strong yellow rainbow rays and small white spark accents':($frame%3===1?'Warm yellow studio with black depth, bold concentric rainbow arcs and clean white highlights':'Yellow-to-gold campaign background with a huge rainbow arch behind the phone') }}"></div>
        <div><label>People</label><select name="people_count"><option value="0">No people</option><option value="1" @selected($frame%3===1)>1 person</option><option value="2" @selected($frame%3===2)>2 people</option><option value="3" @selected($frame%3===0)>3 people</option><option value="4">4 people</option></select></div>
        <div><label>Who / pose / mood</label><input name="people_description" maxlength="500" value="Diverse young adult friends, expressive party-game reactions, premium campaign photography, natural poses"></div>
        <div class="wide"><label>Exact app screen for this phone</label><input type="file" name="app_screen" accept="image/png,image/jpeg,image/webp"><span class="hint">Recommended: upload a real Misery Meter screen so Gemini keeps the UI accurate.</span></div>
      </div>
      <button class="shot-generate" type="submit">Generate frame {{ $frame }} with Gemini</button>
      <div class="shot-status" role="status"></div>
      <a class="btn secondary shot-download" download>Download Apple screenshot</a>
    </form>
  </section>
@endfor
</div>
@endsection

@push('scripts')
<script>
(() => {
const csrf=document.querySelector('meta[name="csrf-token"]').content,generateUrl={{ Illuminate\Support\Js::from(route('cms.screenshots.generate')) }},referenceUrl={{ Illuminate\Support\Js::from(route('cms.screenshots.references.store')) }},assetDeleteBase={{ Illuminate\Support\Js::from(url('/cms/screenshot-maker/references')) }};
const jsonError=async response=>{const payload=await response.json().catch(()=>({}));throw new Error(payload.message||Object.values(payload.errors||{})[0]?.[0]||'Request failed.')} ;
const bindDelete=item=>item.querySelector('button').addEventListener('click',async()=>{if(!confirm('Delete this saved reference?'))return;const response=await fetch(`${assetDeleteBase}/${encodeURIComponent(item.dataset.name)}`,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}});if(!response.ok)return jsonError(response);item.remove()});
document.querySelectorAll('.reference-item').forEach(bindDelete);
document.getElementById('referenceForm').addEventListener('submit',async event=>{event.preventDefault();const button=event.currentTarget.querySelector('button'),original=button.textContent;button.disabled=true;button.textContent='Saving…';try{const response=await fetch(referenceUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf},body:new FormData(event.currentTarget)});if(!response.ok)return await jsonError(response);const payload=await response.json();payload.references.forEach(reference=>{const item=document.createElement('div');item.className='reference-item';item.dataset.name=reference.name;item.innerHTML=`<img src="${reference.url}" alt="App Store style reference"><button type="button" title="Delete">×</button>`;document.getElementById('referenceList').append(item);bindDelete(item)});event.currentTarget.reset()}catch(error){alert(error.message)}finally{button.disabled=false;button.textContent=original}});
document.querySelectorAll('.shot-frame').forEach(section=>{const form=section.querySelector('form'),status=section.querySelector('.shot-status'),preview=section.querySelector('.shot-preview'),download=section.querySelector('.shot-download'),storageKey=`misery-screenshot-frame-${section.dataset.frame}`;try{const saved=JSON.parse(localStorage.getItem(storageKey)||'{}');Object.entries(saved).forEach(([name,value])=>{const field=form.elements.namedItem(name);if(field&&field.type!=='file')field.value=value})}catch{}form.querySelectorAll('input:not([type=file]),textarea,select').forEach(field=>field.addEventListener('input',()=>{const values={};new FormData(form).forEach((value,key)=>{if(typeof value==='string')values[key]=value});localStorage.setItem(storageKey,JSON.stringify(values))}));form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('.shot-generate'),original=button.textContent;button.disabled=true;button.textContent='Gemini is generating…';status.className='shot-status';status.textContent='Sending this frame, app screen and all saved references to Gemini…';try{const response=await fetch(generateUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf},body:new FormData(form)});if(!response.ok)return await jsonError(response);const payload=await response.json();preview.innerHTML=`<img src="${payload.url}?v=${Date.now()}" alt="Generated App Store screenshot">`;download.href=payload.url;download.download=payload.filename;download.classList.add('visible');status.textContent=`Ready · ${payload.provider} · ${payload.width} × ${payload.height}`}catch(error){status.className='shot-status error';status.textContent=error.message||'Generation failed.'}finally{button.disabled=false;button.textContent=original}})});
})();
</script>
@endpush
