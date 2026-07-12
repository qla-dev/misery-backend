<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Misery Index</title>
<style>body{font:16px system-ui;background:#09090b;color:#eee;max-width:520px;margin:40px auto;padding:20px}input,button{box-sizing:border-box;width:100%;padding:12px;margin:6px 0;border-radius:8px;border:1px solid #444}button{background:#facc15;font-weight:800;cursor:pointer}button:disabled{cursor:not-allowed;opacity:.55}section{border:1px solid #333;padding:16px;border-radius:12px;margin-top:14px}.row{display:flex;gap:8px}.hidden{display:none}small,.muted{color:#aaa}.game-row{align-items:center;border-top:1px solid #292929;display:flex;gap:12px;padding:10px 0}.game-row:first-child{border-top:0}.game-info{flex:1;min-width:0}.game-info strong,.game-info small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.quick-join{width:auto;white-space:nowrap}</style></head>
<body><h1>Misery Index</h1><section id="entry"><input id="name" placeholder="Your name"><div class="row"><button onclick="createGame()">Create game</button><button id="join" onclick="joinGame()">Join game</button></div><input id="code" placeholder="Room code"><div id="available"><h2>Available games</h2><p class="muted">Loading games...</p></div></section><section id="room" class="hidden"><h2>Room <span id="roomCode"></span></h2><div id="players"></div><button id="start" onclick="startGame()">Start and deal 3</button></section><section id="game" class="hidden"><h2 id="card"></h2><small>Is this card placed correctly?</small><div class="row"><button onclick="move(true)">Correct</button><button onclick="move(false)">Wrong</button></div><div id="moves"></div></section><p id="message"></p>
<script>
const api='{{ url('/api') }}';let game=null,user=null,joinPending=false;const q=id=>document.getElementById(id);
async function call(path,options={}){const r=await fetch(api+path,{headers:{'Content-Type':'application/json','Accept':'application/json'},...options});const j=await r.json();if(!r.ok)throw Error(j.message||'Request failed');return j.data||j}
async function createGame(){try{set(await call('/games',{method:'POST',body:JSON.stringify({name:q('name').value})}))}catch(e){q('message').textContent=e.message}}
async function joinGame(){
 if(joinPending)return;
 try{
  const code=q('code').value.trim().toUpperCase();if(!code)throw Error('Enter a room code.');
  joinPending=true;setJoinDisabled(true);
  set(await call('/games/code/'+encodeURIComponent(code)+'/join',{method:'POST',body:JSON.stringify({name:q('name').value})}));
 }catch(e){joinPending=false;setJoinDisabled(false);q('message').textContent=e.message}
}
async function quickJoin(code){q('code').value=code;await joinGame()}
function setJoinDisabled(disabled){q('join').disabled=disabled;document.querySelectorAll('.quick-join').forEach(button=>button.disabled=disabled)}
async function loadAvailableGames(){
 if(game)return;
 try{
  const games=await call('/games');
  q('available').innerHTML='<h2>Available games</h2>'+(games.length?games.map(x=>'<div class="game-row"><div class="game-info"><strong>'+escapeHtml(x.members[0]?.name||'Game room')+'</strong><small>'+escapeHtml(x.code)+' &middot; '+x.members.length+'/8 players</small></div><button class="quick-join" onclick="quickJoin(\''+escapeHtml(x.code)+'\')">Quick join</button></div>').join(''):'<p class="muted">No open games right now.</p>');
 }catch(e){q('available').innerHTML='<h2>Available games</h2><p class="muted">Could not load games. Retrying...</p>'}
}
function escapeHtml(value){return String(value).replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]))}
function set(x){game=x.game.data||x.game;user=x.user.data||x.user;q('entry').className='hidden';q('room').className='';render();setInterval(refresh,3000)}
async function refresh(){if(game){game=await call('/games/'+game.id);render()}}
function render(){q('roomCode').textContent=game.code;q('players').innerHTML=game.members.map(x=>'<p>'+x.name+'</p>').join('');q('start').style.display=game.owner_id===user.id?'block':'none';if(game.started){q('room').className='hidden';q('game').className='';q('card').textContent=game.current_card?.title||'No more cards';q('moves').innerHTML=game.moves.map(x=>'<p>'+x.player.name+': '+(x.correct?'Correct':'Wrong')+'</p>').join('')}}
async function startGame(){game=await call('/games/'+game.id+'/start',{method:'POST',body:JSON.stringify({user_id:user.id})});render()}
async function move(correct){const x=await call('/games/'+game.id+'/moves',{method:'POST',body:JSON.stringify({player_id:user.id,correct})});game=x.game.data||x.game;render()}
loadAvailableGames();setInterval(loadAvailableGames,3000);
</script></body></html>
