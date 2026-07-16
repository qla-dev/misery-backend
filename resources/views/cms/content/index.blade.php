@extends('layouts.cms')
@section('title','Content')
@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
.content-toolbar{align-items:flex-end}.content-toolbar h1{font-size:34px;margin:0}.content-toolbar p{font-size:14px;margin:6px 0 0}.content-grid{align-items:start;display:grid;gap:20px;grid-template-columns:minmax(330px,410px) minmax(0,1fr)}.content-controls{display:grid;gap:18px}.content-controls h2,.content-output h2{font-size:17px;margin:0 0 12px}.content-controls h3{font-size:13px;letter-spacing:.08em;margin:0 0 10px;text-transform:uppercase}.content-choice-grid{display:grid;gap:9px;grid-template-columns:repeat(3,1fr)}.content-choice{background:#101011;border:1px solid #363636;border-radius:12px;cursor:pointer;min-height:90px;padding:12px;position:relative;transition:.15s}.content-choice:hover{border-color:#777}.content-choice:has(input:checked){background:#2a2406;border-color:var(--primary);box-shadow:0 0 0 1px rgba(250,204,21,.15)}.content-choice input{height:0;opacity:0;position:absolute;width:0}.content-choice b{display:block;font-size:13px}.content-choice span{color:#8f8f8f;display:block;font-size:11px;line-height:1.35;margin-top:5px}.content-choice:has(input:checked) span{color:#d6c65e}.content-format-grid{display:grid;gap:9px;grid-template-columns:1fr 1fr}.content-format{align-items:center;background:#101011;border:1px solid #363636;border-radius:11px;cursor:pointer;display:flex;gap:11px;padding:11px}.content-format:has(input:checked){border-color:var(--primary);color:var(--primary)}.content-format input{accent-color:var(--primary);width:auto}.content-format i{border:2px solid currentColor;border-radius:3px;display:block;height:29px;width:23px}.content-format.story i{height:34px;width:19px}.content-format small{color:#888;display:block;font-size:10px;margin-top:2px}.content-buttons{display:grid;gap:9px;grid-template-columns:1fr 1fr}.content-buttons button:first-child{grid-column:1/-1}.content-rule{background:#111;border:1px dashed #444;border-radius:11px;padding:12px}.content-rule summary{color:#facc15;cursor:pointer;font-weight:800}.content-rule ul{color:#aaa;font-size:12px;line-height:1.6;margin:10px 0 0;padding-left:18px}.content-status{background:#111;border-radius:9px;color:#aaa;display:none;font-size:12px;padding:10px}.content-status.visible{display:block}.content-status.error{background:#3f1010;color:#fecaca}.content-preview-panel{position:sticky;top:88px}.content-stage{align-items:center;background:radial-gradient(circle at 50% 15%,#27210a 0,#111 35%,#09090b 75%);border:1px solid #303030;border-radius:16px;display:flex;justify-content:center;min-height:680px;overflow:hidden;padding:30px}.social-preview{aspect-ratio:4/5;background:#080808;box-shadow:0 30px 70px rgba(0,0,0,.5);color:#fff;max-height:690px;overflow:hidden;position:relative;width:min(100%,552px)}.social-preview.story{aspect-ratio:9/16;max-height:720px;width:auto}.social-grid{background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:8% 8%;inset:0;mask-image:linear-gradient(to bottom,#000,transparent 72%);position:absolute}.social-accent{background:#facc15;clip-path:polygon(52% 0,100% 0,68% 32%,88% 32%,35% 74%,52% 40%,25% 40%);height:44%;opacity:.96;position:absolute;right:-8%;top:-8%;transform:rotate(4deg);width:44%}.social-preview[data-accent="timeline"] .social-accent{clip-path:none;height:4px;left:8%;right:8%;top:31%;width:auto}.social-preview[data-accent="timeline"] .social-accent:after,.social-preview[data-accent="timeline"] .social-accent:before{background:#facc15;border:4px solid #080808;border-radius:50%;content:"";height:16px;position:absolute;top:-10px;width:16px}.social-preview[data-accent="timeline"] .social-accent:before{left:17%}.social-preview[data-accent="timeline"] .social-accent:after{right:21%}.social-preview[data-accent="spotlight"] .social-accent{background:radial-gradient(circle,#facc15 0,rgba(250,204,21,.24) 34%,transparent 70%);clip-path:none;height:70%;right:-20%;top:35%;width:80%}.social-brand{align-items:center;display:flex;gap:8px;left:7.5%;position:absolute;top:5.5%;z-index:3}.social-brand img{height:34px;width:34px}.social-brand b{font:20px/1 "Bebas Neue";letter-spacing:.11em}.social-copy{left:7.5%;position:absolute;right:7.5%;top:18%;z-index:3}.social-copy .eyebrow{color:#facc15;font:700 12px/1.2 Outfit;letter-spacing:.17em;margin:0 0 14px;text-transform:uppercase}.social-copy h2{font:56px/.9 "Bebas Neue";letter-spacing:.015em;margin:0;max-width:85%;text-transform:uppercase}.social-copy .subtitle{color:#d4d4d4;font:600 14px/1.35 Outfit;margin:17px 0 0;max-width:75%}.social-detail{border-left:3px solid #facc15;bottom:8%;font:700 10px/1.3 Outfit;left:7.5%;letter-spacing:.08em;max-width:48%;padding-left:10px;position:absolute;text-transform:uppercase;z-index:4}.social-cta{bottom:3.6%;color:#facc15;font:700 9px/1 Outfit;letter-spacing:.14em;position:absolute;right:7.5%;text-transform:uppercase;z-index:4}.social-silhouette{bottom:-9%;height:53%;object-fit:contain;position:absolute;z-index:2}.social-preview[data-position="left"] .social-silhouette{left:1%}.social-preview[data-position="center"] .social-silhouette{left:50%;transform:translateX(-50%)}.social-preview[data-position="right"] .social-silhouette{right:1%}.social-preview[data-scale="small"] .social-silhouette{height:42%}.social-preview[data-scale="large"] .social-silhouette{height:62%}.social-preview.story .social-copy{top:16%}.social-preview.story .social-copy h2{font-size:61px;max-width:90%}.social-preview.story .social-copy .subtitle{font-size:15px;max-width:82%}.social-preview.story .social-silhouette{bottom:-3%;height:48%}.social-preview.story[data-scale="small"] .social-silhouette{height:39%}.social-preview.story[data-scale="large"] .social-silhouette{height:57%}.content-output{margin-top:20px}.content-fields{display:grid;gap:12px;grid-template-columns:1fr 1fr}.content-fields .wide{grid-column:1/-1}.content-fields textarea{min-height:78px;resize:vertical}.content-fields label{font-size:12px}.content-fields .triple{display:grid;gap:10px;grid-template-columns:repeat(3,1fr)}.content-export{align-items:center;display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}.content-export .export-main{font-size:15px;padding:13px 19px}.content-source{color:#777;font-size:11px;margin-left:auto;max-width:420px;text-align:right}@media(max-width:900px){.content-grid{grid-template-columns:1fr}.content-preview-panel{position:static}.content-stage{min-height:560px}.social-preview{max-height:600px}.social-preview.story{max-height:680px}}@media(max-width:600px){.content-choice-grid{grid-template-columns:1fr}.content-buttons,.content-fields{grid-template-columns:1fr}.content-buttons button:first-child,.content-fields .wide{grid-column:auto}.content-fields .triple{grid-template-columns:1fr}.content-stage{padding:14px}.social-copy h2{font-size:45px}.content-source{margin-left:0;text-align:left}}
</style>

<div class="toolbar content-toolbar"><div><h1>Content studio</h1><p class="hint">Generate branded Instagram concepts, edit every word, then export a true HQ PNG.</p></div><span class="badge">V1 · EDITORIAL SOCIAL</span></div>

<div class="content-grid">
  <section class="panel content-controls">
    <div>
      <h3>1. Choose an idea</h3>
      <div class="content-choice-grid">
        <label class="content-choice"><input type="radio" name="mode" value="custom" checked><b>Your brief</b><span>Describe the message and composition yourself.</span></label>
        <label class="content-choice"><input type="radio" name="mode" value="explainer"><b>Explain game</b><span>Randomly explain one useful game feature.</span></label>
        <label class="content-choice"><input type="radio" name="mode" value="history"><b>Try your luck</b><span>Turn a real historical disaster into an editorial post.</span></label>
      </div>
    </div>

    <div id="briefField" class="field" style="margin:0">
      <label for="contentBrief">Composition and message</label>
      <textarea id="contentBrief" maxlength="1500" placeholder="Example: Explain stealing with a lonely silhouette waiting at the bottom right. Make the title playful and keep a lot of empty space."></textarea>
      <div class="hint">Describe copy, mood, hierarchy, position, or what the post should teach. AI still follows the locked brand rules.</div>
    </div>

    <div>
      <h3>2. Format and language</h3>
      <div class="content-format-grid">
        <label class="content-format"><input type="radio" name="format" value="post" checked><i></i><span><b>IG Post</b><small>1080 × 1350 · 4:5</small></span></label>
        <label class="content-format story"><input type="radio" name="format" value="story"><i></i><span><b>IG Story</b><small>1080 × 1920 · 9:16</small></span></label>
      </div>
      <label for="contentLanguage" style="margin-top:12px">Copy language</label>
      <select id="contentLanguage"><option value="en">English</option><option value="bs">Bosanski</option></select>
    </div>

    <div class="content-buttons">
      <button id="generateContent" type="button">Generate concept</button>
      <button id="shuffleComposition" class="secondary" type="button">Shuffle composition</button>
      <button id="tryLuck" class="secondary" type="button">Try your luck</button>
    </div>
    <div id="contentStatus" class="content-status" role="status"></div>

    <details class="content-rule">
      <summary>Locked brand guidance</summary>
      <ul><li>Title always uses Bebas Neue.</li><li>Subtitle and supporting text always use Outfit.</li><li>Misery Meter logo is mandatory and cannot be removed.</li><li>Black, white, and misery yellow remain the core palette.</li><li>Exactly one silhouette is anchored near the bottom.</li><li>Social layouts are editorial compositions, never copies of a playing card.</li></ul>
    </details>
  </section>

  <section class="content-preview-panel">
    <div class="content-stage">
      <article id="socialPreview" class="social-preview" data-accent="bolt" data-position="right" data-scale="medium">
        <div class="social-grid"></div><div class="social-accent"></div>
        <div class="social-brand"><img src="{{ asset('misery-logo.svg') }}" alt=""><b>MISERY METER</b></div>
        <div class="social-copy"><p id="previewEyebrow" class="eyebrow">SOCIAL MISERY, EXPLAINED</p><h2 id="previewTitle">ONE BAD DECISION. A GREAT STORY.</h2><p id="previewSubtitle" class="subtitle">Build a sharp social post from one unfortunate idea, one silhouette, and absolutely no good luck.</p></div>
        <img class="social-silhouette" src="{{ route('cms.content.silhouette') }}" alt="">
        <div id="previewDetail" class="social-detail">MADE FOR PEOPLE WHO RANK BAD DAYS</div><div id="previewCta" class="social-cta">TRY YOUR LUCK →</div>
      </article>
    </div>
  </section>
</div>

<section class="panel content-output">
  <div class="toolbar"><div><h2>Edit generated content</h2><p class="hint">Every change updates the preview and HQ export immediately.</p></div></div>
  <div class="content-fields">
    <div><label for="fieldEyebrow">Eyebrow</label><input id="fieldEyebrow" maxlength="28" value="SOCIAL MISERY, EXPLAINED"></div>
    <div><label for="fieldDetail">Detail line</label><input id="fieldDetail" maxlength="72" value="MADE FOR PEOPLE WHO RANK BAD DAYS"></div>
    <div class="wide"><label for="fieldTitle">Title · Bebas Neue</label><textarea id="fieldTitle" maxlength="62">ONE BAD DECISION. A GREAT STORY.</textarea></div>
    <div class="wide"><label for="fieldSubtitle">Subtitle · Outfit</label><textarea id="fieldSubtitle" maxlength="170">Build a sharp social post from one unfortunate idea, one silhouette, and absolutely no good luck.</textarea></div>
    <div><label for="fieldCta">CTA</label><input id="fieldCta" maxlength="34" value="TRY YOUR LUCK →"></div>
    <div class="wide"><label for="fieldCaption">Instagram caption</label><textarea id="fieldCaption" maxlength="500">How bad could it be? Open Misery Meter and find out. #MiseryMeter</textarea></div>
    <div class="wide triple">
      <div><label for="fieldPosition">Silhouette position</label><select id="fieldPosition"><option value="left">Left</option><option value="center">Center</option><option value="right" selected>Right</option></select></div>
      <div><label for="fieldScale">Silhouette scale</label><select id="fieldScale"><option value="small">Small</option><option value="medium" selected>Medium</option><option value="large">Large</option></select></div>
      <div><label for="fieldAccent">Accent</label><select id="fieldAccent"><option value="bolt" selected>Lightning</option><option value="timeline">Timeline</option><option value="spotlight">Spotlight</option></select></div>
    </div>
  </div>
  <div class="content-export"><button id="exportContent" class="export-main" type="button">Export HQ PNG</button><button id="copyCaption" class="secondary" type="button">Copy caption</button><span id="contentSource" class="content-source">Start with your brief, explain a feature, or try your luck.</span></div>
</section>
@endsection

@push('scripts')
<script>
(() => {
const $=id=>document.getElementById(id),preview=$('socialPreview'),status=$('contentStatus');
const fields={eyebrow:$('fieldEyebrow'),title:$('fieldTitle'),subtitle:$('fieldSubtitle'),detail:$('fieldDetail'),cta:$('fieldCta'),caption:$('fieldCaption'),silhouette_position:$('fieldPosition'),silhouette_scale:$('fieldScale'),accent_style:$('fieldAccent')};
const previewFields={eyebrow:$('previewEyebrow'),title:$('previewTitle'),subtitle:$('previewSubtitle'),detail:$('previewDetail'),cta:$('previewCta')};
const checked=name=>document.querySelector(`input[name="${name}"]:checked`).value;
function syncPreview(){Object.keys(previewFields).forEach(key=>previewFields[key].textContent=fields[key].value);preview.dataset.position=fields.silhouette_position.value;preview.dataset.scale=fields.silhouette_scale.value;preview.dataset.accent=fields.accent_style.value;preview.classList.toggle('story',checked('format')==='story')}
Object.values(fields).forEach(field=>field.addEventListener('input',syncPreview));document.querySelectorAll('input[name="format"]').forEach(input=>input.addEventListener('change',syncPreview));
document.querySelectorAll('input[name="mode"]').forEach(input=>input.addEventListener('change',()=>{$('briefField').style.display=checked('mode')==='custom'?'block':'none'}));
function setStatus(message,error=false){status.textContent=message;status.className='content-status visible'+(error?' error':'')}
function applyContent(content){Object.entries(fields).forEach(([key,field])=>{if(content[key]!==undefined)field.value=content[key]});syncPreview()}
function shuffleComposition(){const positions=['left','center','right'],scales=['small','medium','large'],accents=['bolt','timeline','spotlight'];fields.silhouette_position.value=positions[Math.floor(Math.random()*positions.length)];fields.silhouette_scale.value=scales[Math.floor(Math.random()*scales.length)];fields.accent_style.value=accents[Math.floor(Math.random()*accents.length)];syncPreview()}
$('shuffleComposition').addEventListener('click',shuffleComposition);
async function generate(forceHistory=false){if(forceHistory){document.querySelector('input[name="mode"][value="history"]').checked=true;$('briefField').style.display='none'}const mode=checked('mode'),brief=$('contentBrief').value.trim();if(mode==='custom'&&!brief){setStatus('Explain the composition or message first.',true);$('contentBrief').focus();return}const button=$('generateContent'),original=button.textContent;button.disabled=true;button.textContent='Generating…';setStatus('Building a branded social concept…');try{const response=await fetch({{ Illuminate\Support\Js::from(route('cms.content.generate')) }},{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({mode,brief,format:checked('format'),language:$('contentLanguage').value})});const payload=await response.json();if(!response.ok)throw new Error(payload.message||Object.values(payload.errors||{})[0]?.[0]||'Generation failed.');applyContent(payload.content);$('contentSource').textContent=`${payload.provider} · ${payload.source}`;setStatus('Concept ready. Edit anything below or export it now.')}catch(error){setStatus(error.message||'Generation failed.',true)}finally{button.disabled=false;button.textContent=original}}
$('generateContent').addEventListener('click',()=>generate(false));$('tryLuck').addEventListener('click',()=>generate(true));
$('copyCaption').addEventListener('click',async()=>{await navigator.clipboard.writeText(fields.caption.value);const button=$('copyCaption'),label=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=label,1200)});
function loadImage(source){return new Promise((resolve,reject)=>{const image=new Image();image.onload=()=>resolve(image);image.onerror=()=>reject(new Error('Brand asset could not be loaded.'));image.src=source})}
function linesFor(ctx,text,maxWidth,maxLines){const words=String(text||'').trim().split(/\s+/),lines=[];let line='';for(const word of words){const next=line?line+' '+word:word;if(!line||ctx.measureText(next).width<=maxWidth){line=next;continue}lines.push(line);line=word;if(lines.length===maxLines-1)break}if(line&&lines.length<maxLines)lines.push(line);return lines}
function fittedLines(ctx,text,maxWidth,maxLines,startSize,minSize,family,weight='400'){let size=startSize,lines=[];do{ctx.font=`${weight} ${size}px "${family}"`;lines=linesFor(ctx,text,maxWidth,maxLines);if(lines.join(' ').split(/\s+/).length>=String(text).trim().split(/\s+/).length)break;size-=4}while(size>=minSize);return{lines,size}}
function drawTracking(ctx,text,x,y,tracking){const chars=[...String(text)];let width=chars.reduce((sum,char)=>sum+ctx.measureText(char).width,0)+Math.max(0,chars.length-1)*tracking;let cursor=x-width/2;for(const char of chars){ctx.fillText(char,cursor,y);cursor+=ctx.measureText(char).width+tracking}}
function drawAccent(ctx,type,w,h){ctx.save();ctx.fillStyle='#facc15';if(type==='timeline'){ctx.fillRect(w*.075,h*.285,w*.85,5);for(const x of [w*.24,w*.73]){ctx.beginPath();ctx.arc(x,h*.287,15,0,Math.PI*2);ctx.fillStyle='#080808';ctx.fill();ctx.beginPath();ctx.arc(x,h*.287,9,0,Math.PI*2);ctx.fillStyle='#facc15';ctx.fill()}}else if(type==='spotlight'){const gradient=ctx.createRadialGradient(w*.78,h*.72,0,w*.78,h*.72,w*.52);gradient.addColorStop(0,'rgba(250,204,21,.95)');gradient.addColorStop(.3,'rgba(250,204,21,.2)');gradient.addColorStop(1,'rgba(250,204,21,0)');ctx.fillStyle=gradient;ctx.fillRect(0,0,w,h)}else{ctx.beginPath();ctx.moveTo(w*.69,-30);ctx.lineTo(w*1.03,-30);ctx.lineTo(w*.82,h*.2);ctx.lineTo(w*.96,h*.2);ctx.lineTo(w*.58,h*.49);ctx.lineTo(w*.7,h*.25);ctx.lineTo(w*.54,h*.25);ctx.closePath();ctx.fill()}ctx.restore()}
async function exportPng(){const button=$('exportContent'),original=button.textContent;button.disabled=true;button.textContent='Rendering HQ…';try{await Promise.all([document.fonts.load('120px "Bebas Neue"'),document.fonts.load('40px Outfit')]);const [logo,silhouette]=await Promise.all([loadImage({{ Illuminate\Support\Js::from(asset('misery-logo.svg')) }}),loadImage({{ Illuminate\Support\Js::from(route('cms.content.silhouette')) }})]);const story=checked('format')==='story',w=1080,h=story?1920:1350,canvas=document.createElement('canvas');canvas.width=w;canvas.height=h;const ctx=canvas.getContext('2d');ctx.fillStyle='#080808';ctx.fillRect(0,0,w,h);ctx.strokeStyle='rgba(255,255,255,.035)';ctx.lineWidth=1;for(let x=0;x<w;x+=w/12){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,h*.74);ctx.stroke()}for(let y=0;y<h*.74;y+=h/16){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(w,y);ctx.stroke()}drawAccent(ctx,fields.accent_style.value,w,h);
const margin=82;ctx.drawImage(logo,margin,story?92:72,78,78);ctx.fillStyle='#fff';ctx.font='48px "Bebas Neue"';ctx.textBaseline='middle';drawTracking(ctx,'MISERY METER',margin+265,(story?92:72)+39,5);
const eyebrowY=story?330:270;ctx.textAlign='left';ctx.textBaseline='top';ctx.fillStyle='#facc15';ctx.font='700 28px Outfit';const eyebrow=fields.eyebrow.value.toUpperCase();let cursor=margin;for(const char of eyebrow){ctx.fillText(char,cursor,eyebrowY);cursor+=ctx.measureText(char).width+4}
const titleY=eyebrowY+76,titleFit=fittedLines(ctx,fields.title.value.toUpperCase(),w-margin*2,story?4:3,story?132:120,72,'Bebas Neue');ctx.fillStyle='#f8f8f5';ctx.font=`${titleFit.size}px "Bebas Neue"`;const titleLineHeight=titleFit.size*.88;titleFit.lines.forEach((line,index)=>ctx.fillText(line,margin,titleY+index*titleLineHeight));
const subtitleY=titleY+titleFit.lines.length*titleLineHeight+42,subtitleFit=fittedLines(ctx,fields.subtitle.value,w*.7,4,story?39:35,25,'Outfit','600');ctx.fillStyle='#d4d4d4';ctx.font=`600 ${subtitleFit.size}px Outfit`;const subtitleLineHeight=subtitleFit.size*1.35;subtitleFit.lines.forEach((line,index)=>ctx.fillText(line,margin,subtitleY+index*subtitleLineHeight));
const sizes={small:.34,medium:.43,large:.52},silhouetteHeight=h*sizes[fields.silhouette_scale.value],silhouetteWidth=silhouetteHeight*(silhouette.naturalWidth/silhouette.naturalHeight),silhouetteY=h-silhouetteHeight+(story?25:55);let silhouetteX=margin;if(fields.silhouette_position.value==='center')silhouetteX=(w-silhouetteWidth)/2;if(fields.silhouette_position.value==='right')silhouetteX=w-margin-silhouetteWidth;ctx.drawImage(silhouette,silhouetteX,silhouetteY,silhouetteWidth,silhouetteHeight);
const detailY=h-(story?170:132);ctx.fillStyle='#facc15';ctx.fillRect(margin,detailY,5,55);ctx.fillStyle='#fff';ctx.font='700 23px Outfit';ctx.fillText(fields.detail.value.toUpperCase(),margin+25,detailY+12);ctx.textAlign='right';ctx.fillStyle='#facc15';ctx.font='700 22px Outfit';ctx.fillText(fields.cta.value.toUpperCase(),w-margin,h-(story?120:82));
const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!blob)throw new Error('PNG rendering failed.');const link=document.createElement('a'),slug=fields.title.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,50)||'misery-content';link.href=URL.createObjectURL(blob);link.download=`${slug}-${story?'story':'post'}-hq.png`;link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1500);setStatus(`Exported ${w} × ${h} PNG.`)}catch(error){setStatus(error.message||'Export failed.',true)}finally{button.disabled=false;button.textContent=original}}
$('exportContent').addEventListener('click',exportPng);syncPreview();
})();
</script>
@endpush
