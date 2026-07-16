@extends('layouts.cms')
@section('title','Cards')
@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@400;700&display=swap" rel="stylesheet">
<style>.inline-score{font-variant-numeric:tabular-nums}.inline-score__value{border-bottom:1px dashed #777;cursor:pointer;display:inline-block;min-width:48px;user-select:none}.inline-score__input{padding:7px;width:88px}.inline-score.is-saving{opacity:.55}.inline-score.is-error .inline-score__input{border-color:#ef4444}.inline-score__error{color:#f87171;display:block;font-size:11px;margin-top:4px;max-width:150px}.art-thumb{display:inline-block;position:relative}.art-thumb .thumb{display:block}.art-thumb__zoom{background:#111;border:1px solid #555;bottom:-4px;color:#fff;font-size:11px;padding:4px 6px;position:absolute;right:-7px}.enhanced-flag{background:#713f12;color:#fde68a;display:block;margin-top:8px;text-align:center}.enhanced-flag[hidden]{display:none}.actions button:disabled{cursor:not-allowed;opacity:.4}</style>
<div class="toolbar"><div><h1>Cards</h1><p class="hint">Double-click a score to edit it. Press Enter or click outside to save.</p></div><a class="btn" href="{{ route('cms.cards.create') }}">+ New card</a></div>
<form class="toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search title or description"><select name="stack" style="max-width:180px"><option value="">All packs</option>@foreach($stacks as $stack)<option value="{{ $stack->id }}" @selected((string)request('stack')===(string)$stack->id)>{{ $stack->name }}</option>@endforeach</select><select name="status" style="max-width:180px"><option value="">All statuses</option><option value="0" @selected(request('status')==='0')>Drafts</option><option value="1" @selected(request('status')==='1')>Approved</option></select><button>Filter</button></form>
<div class="panel table-wrap"><table><thead><tr><th>Art</th><th>Card</th><th>Score</th><th>Status</th><th>Stack</th><th></th></tr></thead><tbody>
@forelse($cards as $card)
@php($image = $card->image && $card->image !== '0' ? (str_starts_with($card->image,'http') ? $card->image : url('/card-images/'.preg_replace('#^storage/#','',$card->image))) : null)
@php($canEnhance = $image && !str_starts_with($card->image,'http://') && !str_starts_with($card->image,'https://'))
<tr><td><div class="art-thumb"><a href="{{ route('cms.cards.edit',['card'=>$card,'return'=>request()->fullUrl()]) }}" title="Edit {{ $card->title }}">@if($image)<img id="card-art-{{ $card->id }}" class="thumb" src="{{ $image }}" alt="Artwork for {{ $card->title }}">@else<div class="thumb placeholder">⚡</div>@endif</a>@if($image)<button class="art-thumb__zoom" type="button" data-zoom-artwork data-zoom-source="{{ $image }}" aria-label="Zoom artwork for {{ $card->title }}" title="Zoom artwork">Zoom</button>@endif</div><span class="badge enhanced-flag" @if(!$card->artwork_enhanced) hidden @endif>ENHANCED</span></td>
<td><b>{{ $card->title }}</b><div class="hint">{{ Str::limit($card->subtitle,70) }}</div></td><td><div class="inline-score" data-url="{{ route('cms.cards.score',$card) }}" data-score="{{ number_format($card->score,2,'.','') }}"><span class="inline-score__value" title="Double-click to edit" tabindex="0">{{ number_format($card->score,2) }}</span><input class="inline-score__input" type="number" min="0" max="100" step="0.01" inputmode="decimal" aria-label="Score for {{ $card->title }}" hidden><span class="inline-score__error" role="alert" hidden></span></div></td><td><span class="badge" style="background:{{ $card->status?'#14532d':'#3f3f46' }}">{{ $card->status?'APPROVED':'DRAFT' }}</span></td><td><span class="badge">{{ $card->stack?->name ?? $card->deck }}</span></td>
<td><div class="actions"><form method="post" action="{{ route('cms.cards.status',$card) }}">@csrf<input type="hidden" name="status" value="{{ $card->status?0:1 }}"><button class="secondary">{{ $card->status?'Unapprove':'Approve' }}</button></form><button class="secondary artwork-action" type="button" data-mode="{{ $canEnhance?'enhance':'generate' }}" data-url="{{ $canEnhance?route('cms.cards.enhance-artwork',$card):route('cms.cards.generate',$card) }}" data-enhance-url="{{ route('cms.cards.enhance-artwork',$card) }}">{{ $canEnhance?'Enhance':'Generate' }}</button><button class="secondary export-card" type="button" data-title="{{ $card->title }}" data-subtitle="{{ $card->subtitle }}" data-score="{{ number_format($card->score,2,'.','') }}" data-image="{{ $image ?: route('cms.native-card-artwork') }}" data-fallback="{{ $image ? '0' : '1' }}">Export</button><form method="post" action="{{ route('cms.cards.destroy',$card) }}" onsubmit="return confirm('Delete this card?')">@csrf @method('DELETE')<button class="danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="5">No cards found.</td></tr>@endforelse
</tbody></table></div>{{ $cards->links('cms.pagination') }}
@endsection
@push('scripts')
<script>
const CARD_EXPORT_WIDTH=1200,CARD_EXPORT_HEIGHT=1800,CARD_EXPORT_SCALE=CARD_EXPORT_WIDTH/360;
function roundedRect(ctx,x,y,width,height,radius){const r=Math.min(radius,width/2,height/2);ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+width,y,x+width,y+height,r);ctx.arcTo(x+width,y+height,x,y+height,r);ctx.arcTo(x,y+height,x,y,r);ctx.arcTo(x,y,x+width,y,r);ctx.closePath()}
function fitLines(ctx,text,maxWidth,maxLines){const words=String(text||'').trim().split(/\s+/).filter(Boolean),lines=[];let line='';for(const word of words){const candidate=line?line+' '+word:word;if(ctx.measureText(candidate).width<=maxWidth||!line){line=candidate;continue}lines.push(line);line=word;if(lines.length===maxLines-1)break}if(line&&lines.length<maxLines)lines.push(line);const consumed=lines.join(' ').split(/\s+/).length;if(consumed<words.length&&lines.length){let last=lines.length-1;while(lines[last]&&ctx.measureText(lines[last]+'…').width>maxWidth)lines[last]=lines[last].slice(0,-1);lines[last]+='…'}return lines}
function drawCenteredLines(ctx,text,y,maxWidth,maxLines,lineHeight){const lines=fitLines(ctx,text,maxWidth,maxLines);lines.forEach((line,index)=>ctx.fillText(line,CARD_EXPORT_WIDTH/2,y+(index*lineHeight)));return lines.length}
function loadExportImage(source){return new Promise((resolve,reject)=>{const image=new Image();if(new URL(source,location.href).origin!==location.origin)image.crossOrigin='anonymous';image.onload=()=>resolve(image);image.onerror=()=>reject(new Error('Artwork could not be loaded for PNG export.'));image.src=source})}
function drawCover(ctx,image,x,y,width,height){const scale=Math.max(width/image.naturalWidth,height/image.naturalHeight),sourceWidth=width/scale,sourceHeight=height/scale,sourceX=(image.naturalWidth-sourceWidth)/2,sourceY=(image.naturalHeight-sourceHeight)/2;ctx.drawImage(image,sourceX,sourceY,sourceWidth,sourceHeight,x,y,width,height)}
function drawContain(ctx,image,x,y,width,height,scale=.78,translateY=33){const boxWidth=width*scale,boxHeight=height*scale,imageScale=Math.min(boxWidth/image.naturalWidth,boxHeight/image.naturalHeight),drawWidth=image.naturalWidth*imageScale,drawHeight=image.naturalHeight*imageScale;ctx.drawImage(image,x+(width-drawWidth)/2,y+(height-drawHeight)/2+translateY,drawWidth,drawHeight)}
async function exportNativeCard(button){const original=button.textContent;button.disabled=true;button.textContent='Exporting…';try{await Promise.all([document.fonts.load('100px "Bebas Neue"'),document.fonts.load('37px Outfit')]);const image=await loadExportImage(button.dataset.image);const canvas=document.createElement('canvas');canvas.width=CARD_EXPORT_WIDTH;canvas.height=CARD_EXPORT_HEIGHT;const ctx=canvas.getContext('2d');ctx.save();roundedRect(ctx,0,0,CARD_EXPORT_WIDTH,CARD_EXPORT_HEIGHT,60);ctx.clip();ctx.fillStyle='#000';ctx.fillRect(0,0,CARD_EXPORT_WIDTH,CARD_EXPORT_HEIGHT);
const contentX=60,contentWidth=CARD_EXPORT_WIDTH-120,titleTop=107;ctx.textAlign='center';ctx.textBaseline='top';ctx.fillStyle='#f8f8f5';ctx.font='100px "Bebas Neue"';const titleLines=fitLines(ctx,button.dataset.title,contentWidth-80,3);titleLines.forEach((line,index)=>ctx.fillText(line,CARD_EXPORT_WIDTH/2,titleTop+index*103));const titleBottom=titleTop+titleLines.length*103;const subtitleBoxTop=titleBottom,subtitleBoxHeight=183;ctx.fillStyle='#8f8f8f';ctx.font='37px Outfit';const subtitleLines=fitLines(ctx,button.dataset.subtitle,contentWidth*.88,3),subtitleLineHeight=50,subtitleStart=subtitleBoxTop+(subtitleBoxHeight-subtitleLines.length*subtitleLineHeight)/2;subtitleLines.forEach((line,index)=>ctx.fillText(line,CARD_EXPORT_WIDTH/2,subtitleStart+index*subtitleLineHeight));
const artworkX=60,artworkY=subtitleBoxTop+subtitleBoxHeight,artworkSize=1080;ctx.save();ctx.beginPath();ctx.rect(artworkX,artworkY,artworkSize,artworkSize);ctx.clip();ctx.fillStyle='#000';ctx.fillRect(artworkX,artworkY,artworkSize,artworkSize);button.dataset.fallback==='1'?drawContain(ctx,image,artworkX,artworkY,artworkSize,artworkSize):drawCover(ctx,image,artworkX,artworkY,artworkSize,artworkSize);ctx.restore();
const overlay=ctx.createLinearGradient(144,0,984,CARD_EXPORT_HEIGHT);overlay.addColorStop(0,'rgba(36,36,36,.62)');overlay.addColorStop(.13,'rgba(17,17,17,.34)');overlay.addColorStop(.3,'rgba(0,0,0,.08)');overlay.addColorStop(1,'rgba(0,0,0,0)');ctx.fillStyle=overlay;ctx.fillRect(0,0,CARD_EXPORT_WIDTH,CARD_EXPORT_HEIGHT);
const footerHeight=347,footerTop=CARD_EXPORT_HEIGHT-footerHeight,tabWidth=373,tabX=(CARD_EXPORT_WIDTH-tabWidth)/2;ctx.fillStyle='#000';ctx.fillRect(0,footerTop,CARD_EXPORT_WIDTH,footerHeight);ctx.strokeStyle='rgba(251,191,36,.35)';ctx.lineWidth=7;roundedRect(ctx,30,30,CARD_EXPORT_WIDTH-60,CARD_EXPORT_HEIGHT-60,37);ctx.stroke();ctx.fillStyle='#facc15';ctx.fillRect(tabX,footerTop+113,tabWidth,234);ctx.fillStyle='#facc15';ctx.font='50px "Bebas Neue"';ctx.fillText('MISERY RATE',CARD_EXPORT_WIDTH/2,footerTop+31);ctx.fillStyle='#09090b';ctx.font='140px "Bebas Neue"';ctx.textBaseline='middle';ctx.fillText(Number(button.dataset.score).toFixed(2),CARD_EXPORT_WIDTH/2,footerTop+230);ctx.restore();ctx.strokeStyle='#facc15';ctx.lineWidth=17;roundedRect(ctx,9,9,CARD_EXPORT_WIDTH-18,CARD_EXPORT_HEIGHT-18,51);ctx.stroke();const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!blob)throw new Error('PNG creation failed.');const link=document.createElement('a'),slug=(button.dataset.title||'misery-card').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');link.href=URL.createObjectURL(blob);link.download=(slug||'misery-card')+'-hq.png';link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1000)}catch(error){alert(error.message||'Card export failed.')}finally{button.disabled=false;button.textContent=original}}
document.querySelectorAll('.export-card').forEach(button=>button.addEventListener('click',()=>exportNativeCard(button)));
const csrfToken=document.querySelector('meta[name="csrf-token"]').content;
document.querySelectorAll('.artwork-action').forEach(button=>button.addEventListener('click',async()=>{
    const row=button.closest('tr'),exportButton=row.querySelector('.export-card'),enhancedFlag=row.querySelector('.enhanced-flag'),mode=button.dataset.mode;
    button.disabled=true;button.textContent=mode==='generate'?'Generating…':'Enhancing…';
    try{
        const response=await fetch(button.dataset.url,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}}),data=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(data.message||'Artwork enhancement failed.');
        const refreshedImage=`${data.image}${data.image.includes('?')?'&':'?'}v=${Date.now()}`;
        let thumb=row.querySelector('.thumb'),zoom=row.querySelector('[data-zoom-artwork]');
        if(!thumb.matches('img')){const image=document.createElement('img');image.className='thumb';image.alt='Card artwork';thumb.replaceWith(image);thumb=image}
        thumb.src=refreshedImage;
        if(!zoom){zoom=document.createElement('button');zoom.className='art-thumb__zoom';zoom.type='button';zoom.setAttribute('data-zoom-artwork','');zoom.setAttribute('aria-label','Zoom card artwork');zoom.title='Zoom artwork';zoom.textContent='Zoom';row.querySelector('.art-thumb').appendChild(zoom)}
        zoom.dataset.zoomSource=refreshedImage;exportButton.dataset.image=refreshedImage;exportButton.dataset.fallback='0';
        if(mode==='enhance')enhancedFlag.hidden=false;else{button.dataset.mode='enhance';button.dataset.url=button.dataset.enhanceUrl}
    }catch(error){alert(error.message||(mode==='generate'?'Artwork generation failed.':'Artwork enhancement failed.'))}
    finally{button.disabled=false;button.textContent=button.dataset.mode==='generate'?'Generate':'Enhance'}
}));
document.querySelectorAll('.inline-score').forEach(editor=>{
    const value=editor.querySelector('.inline-score__value'),input=editor.querySelector('.inline-score__input'),error=editor.querySelector('.inline-score__error'),exportButton=editor.closest('tr').querySelector('.export-card');
    let editing=false,saving=false;
    const showError=message=>{editor.classList.add('is-error');error.textContent=message;error.hidden=false};
    const clearError=()=>{editor.classList.remove('is-error');error.hidden=true;error.textContent=''};
    const begin=()=>{if(editing||saving)return;editing=true;clearError();input.value=editor.dataset.score;value.hidden=true;input.hidden=false;input.focus();input.select()};
    const finish=()=>{editing=false;input.hidden=true;value.hidden=false};
    const save=async()=>{
        if(!editing||saving)return;
        const score=input.value.trim();
        if(score===''||!Number.isFinite(Number(score))||Number(score)<0||Number(score)>100){showError('Enter a score from 0 to 100.');input.focus();return}
        saving=true;editor.classList.add('is-saving');clearError();
        try{
            const response=await fetch(editor.dataset.url,{method:'PATCH',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({score})});
            const data=await response.json().catch(()=>({}));
            if(!response.ok)throw new Error(data.message||data.errors?.score?.[0]||'Score could not be saved.');
            editor.dataset.score=data.formatted_score;value.textContent=data.formatted_score;if(exportButton)exportButton.dataset.score=data.formatted_score;finish();
        }catch(saveError){showError(saveError.message||'Score could not be saved.');input.focus()}
        finally{saving=false;editor.classList.remove('is-saving')}
    };
    value.addEventListener('dblclick',begin);
    value.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();begin()}});
    input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();save()}else if(event.key==='Escape'){event.preventDefault();clearError();finish();value.focus()}});
    input.addEventListener('blur',save);
});
</script>
@endpush
