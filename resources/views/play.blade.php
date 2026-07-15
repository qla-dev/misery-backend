<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Misery Index</title>
<style>body{font:16px system-ui;background:#09090b;color:#eee;max-width:650px;margin:40px auto;padding:20px}input,button{box-sizing:border-box;width:100%;padding:12px;margin:6px 0;border-radius:8px;border:1px solid #444}button{background:#facc15;font-weight:800;cursor:pointer}button:disabled{cursor:not-allowed;opacity:.55}button.secondary{background:#262626;color:#eee}button.danger{background:#7f1d1d;border-color:#ef4444;color:#fff}section{border:1px solid #333;padding:16px;border-radius:12px;margin-top:14px}.row{display:flex;gap:8px}.hidden{display:none!important}small,.muted{color:#aaa}.game-row{align-items:center;border-top:1px solid #292929;display:flex;flex-wrap:wrap;gap:12px;padding:10px 0}.game-row:first-child{border-top:0}.game-info{flex:1;min-width:0}.game-info strong,.game-info small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.game-actions{display:flex;flex:0 0 auto;gap:7px}.game-actions button{margin:0;padding:10px 12px;width:auto;white-space:nowrap}.available-members{background:#121214;border:1px solid #303030;border-radius:8px;flex:0 0 100%;padding:8px 12px}.available-members span{display:block;padding:3px 0}.player-tabs{display:flex;gap:10px;margin:0 -2px 2px;overflow-x:auto;padding:2px 2px 8px}.player-tab{align-items:center;background:#171717;border:1px solid #404040;border-radius:11px;display:flex;flex:0 0 auto;gap:8px;padding:10px 12px;transition:border-color .2s,background .2s,box-shadow .2s}.player-tab.active{background:rgba(250,204,21,.15);border-color:#facc15;box-shadow:0 0 0 1px rgba(250,204,21,.35)}.player-tab.me{border-style:dashed}.player-dot{border:1px solid rgba(255,255,255,.3);border-radius:50%;height:10px;width:10px}.player-score{background:rgba(0,0,0,.35);border-radius:5px;color:#d4d4d4;font:11px ui-monospace,monospace;padding:3px 6px}.turn-status{color:#facc15;font-size:12px;font-weight:900;letter-spacing:1.5px;margin:0 0 12px;text-transform:uppercase}.turn-status.waiting{color:#a3a3a3}.error-modal{align-items:center;background:rgba(0,0,0,.82);display:flex;inset:0;justify-content:center;padding:20px;position:fixed;z-index:100}.error-card{background:#18181b;border:1px solid #ef4444;border-radius:14px;box-shadow:0 20px 60px #000;padding:20px;width:min(380px,100%)}.error-card h2{color:#f87171;margin-top:0}.error-card p{color:#ddd;overflow-wrap:anywhere}@media(max-width:560px){.game-info{flex-basis:100%}.game-actions{width:100%}.game-actions button{flex:1}}</style></head>
<body><h1>Misery Index</h1><section id="entry"><input id="name" placeholder="Your name"><div class="row"><button onclick="createGame()">Create game</button><button id="join" onclick="joinGame()">Join game</button></div><input id="code" placeholder="Room code"><div id="available"><h2>Available games</h2><p class="muted">Loading games...</p></div></section><section id="room" class="hidden"><h2>Room <span id="roomCode"></span></h2><div id="players"></div><button id="start" onclick="startGame()">Start and deal 3</button><button id="deleteRoom" class="danger hidden" onclick="deleteRoom()">Kill and delete room</button></section><section id="game" class="hidden"><div id="playerTabs" class="player-tabs"></div><p id="turnStatus" class="turn-status"></p><h2 id="card"></h2><small>Is this card placed correctly?</small><div id="answerActions" class="row"><button onclick="move(true)">Correct</button><button onclick="move(false)">Wrong</button></div><button id="finishTurn" class="hidden" onclick="finishTurn()">Finish Turn</button><div id="moves"></div></section><p id="message"></p><div id="errorModal" class="error-modal hidden" role="dialog" aria-modal="true"><div class="error-card"><h2>Request failed</h2><p id="errorMessage"></p><button onclick="closeApiError()">Close</button></div></div>
<script>
const api='{{ url('/api') }}';let game=null,user=null,joinPending=false,refreshTimer=null,finishTurnPending=false;const q=id=>document.getElementById(id);
async function call(path,options={}){const r=await fetch(api+path,{headers:{'Content-Type':'application/json','Accept':'application/json'},...options});const text=await r.text();const j=text?JSON.parse(text):null;if(!r.ok)throw Error(j?.message||'Request failed');return j?.data??j}
function showApiError(error){q('errorMessage').textContent=error?.message||String(error)||'Request failed';q('errorModal').classList.remove('hidden')}
function closeApiError(){q('errorModal').classList.add('hidden')}
async function createGame(){try{set(await call('/games',{method:'POST',body:JSON.stringify({name:q('name').value})}))}catch(e){showApiError(e)}}
async function joinGame(){
 if(joinPending)return;
 try{
  const code=q('code').value.trim().toUpperCase();if(!code)throw Error('Enter a room code.');
  joinPending=true;setJoinDisabled(true);
  set(await call('/games/code/'+encodeURIComponent(code)+'/join',{method:'POST',body:JSON.stringify({name:q('name').value})}));
 }catch(e){joinPending=false;setJoinDisabled(false);showApiError(e)}
}
async function quickJoin(code){q('code').value=code;await joinGame()}
function setJoinDisabled(disabled){q('join').disabled=disabled;document.querySelectorAll('.quick-join').forEach(button=>button.disabled=disabled)}
async function loadAvailableGames(){
 if(game)return;
 try{
  const games=await call('/games');
  q('available').innerHTML='<h2>Available games</h2>'+(games.length?games.map(x=>'<div class="game-row"><div class="game-info"><strong>'+escapeHtml(x.members[0]?.name||'Game room')+'</strong><small>'+escapeHtml(x.code)+' &middot; '+x.members.length+'/8 players</small></div><div class="game-actions"><button class="secondary" onclick="toggleRoomMembers('+Number(x.id)+',this)">Members</button><button class="danger" onclick="killAvailableRoom('+Number(x.id)+')">Kill</button><button class="quick-join" onclick="quickJoin(\''+escapeHtml(x.code)+'\')">Join</button></div><div id="available-members-'+Number(x.id)+'" class="available-members hidden">'+x.members.map(member=>'<span>'+escapeHtml(member.name)+'</span>').join('')+'</div></div>').join(''):'<p class="muted">No open games right now.</p>');
 }catch(e){q('available').innerHTML='<h2>Available games</h2><p class="muted">Could not load games. Retrying...</p>'}
}
function toggleRoomMembers(id,button){const list=q('available-members-'+id);if(!list)return;const opening=list.classList.contains('hidden');list.classList.toggle('hidden');button.textContent=opening?'Hide members':'Members'}
async function killAvailableRoom(id){if(!confirm('Kill and permanently delete this room?'))return;try{await call('/games/'+id,{method:'DELETE'});await loadAvailableGames()}catch(e){showApiError(e)}}
function escapeHtml(value){return String(value).replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]))}
function playerColor(value){return ({yellow:'#facc15',blue:'#60a5fa',emerald:'#10b981',purple:'#c084fc',rose:'#ef4444',red:'#ef4444',orange:'#f97316',brown:'#8b5a2b',silver:'#d4d4d4'})[value]||'#facc15'}
function set(x){game=x.game.data||x.game;user=x.user.data||x.user;q('entry').className='hidden';q('room').className='';render();if(refreshTimer)clearInterval(refreshTimer);refreshTimer=setInterval(refresh,3000)}
async function refresh(){if(game&&user){game=await call('/games/'+game.id+'?user_id='+encodeURIComponent(user.id));render()}}
function render(){
 q('roomCode').textContent=game.code;
 q('players').innerHTML=game.members.map(x=>'<p>'+escapeHtml(x.name)+'</p>').join('');
 const isOwner=Number(game.owner_id)===Number(user.id);
 q('start').style.display=isOwner?'block':'none';
 q('deleteRoom').className=isOwner?'danger':'danger hidden';
 if(!game.started)return;
 q('room').className='hidden';q('game').className='';q('card').textContent=game.current_card?.title||'No more cards';
 const activeId=Number(game.current_player_id);const isMyTurn=activeId===Number(user.id);const activePlayer=game.members.find(x=>Number(x.id)===activeId);
 q('playerTabs').innerHTML=game.members.map(x=>{const active=Number(x.id)===activeId;const mine=Number(x.id)===Number(user.id);const points=(game.hands?.[x.id]||[]).length;return '<div class="player-tab'+(active?' active':'')+(mine?' me':'')+'"><span class="player-dot" style="background:'+playerColor(x.color)+'"></span><strong>'+escapeHtml(x.name)+'</strong><span class="player-score">'+points+' pts</span></div>'}).join('');
 q('turnStatus').textContent=activePlayer?(isMyTurn?(game.is_steal_turn?'Your steal turn':'Your turn'):(game.is_steal_turn?activePlayer.name+' can steal':activePlayer.name+"'s turn")):'Waiting for next turn';
 q('turnStatus').className='turn-status'+(isMyTurn?'':' waiting');
 q('answerActions').className=game.awaiting_finish?'row hidden':'row';
 q('answerActions').querySelectorAll('button').forEach(button=>button.disabled=!isMyTurn||game.awaiting_finish);
 q('finishTurn').className='hidden';
 q('moves').innerHTML=game.moves.map(x=>'<p>'+escapeHtml(x.player.name)+': '+(x.correct?'Correct':'Wrong')+'</p>').join('');
 if(game.awaiting_finish&&isMyTurn){q('turnStatus').textContent='Finishing turn...';void finishTurn()}
}
async function deleteRoom(){if(!game||!user||Number(game.owner_id)!==Number(user.id))return;if(!confirm('Kill and permanently delete this room?'))return;try{await call('/games/'+game.id,{method:'DELETE'});if(refreshTimer)clearInterval(refreshTimer);refreshTimer=null;game=null;user=null;joinPending=false;q('room').className='hidden';q('game').className='hidden';q('entry').className='';setJoinDisabled(false);q('code').value='';await loadAvailableGames()}catch(e){showApiError(e)}}
async function startGame(){try{game=await call('/games/'+game.id+'/start',{method:'POST',body:JSON.stringify({user_id:user.id,target_score:5})});render()}catch(e){showApiError(e)}}
async function move(correct){try{const x=await call('/games/'+game.id+'/moves',{method:'POST',body:JSON.stringify({player_id:user.id,correct})});game=x.game.data||x.game;render()}catch(e){showApiError(e)}}
async function finishTurn(){if(finishTurnPending)return;finishTurnPending=true;try{const x=await call('/games/'+game.id+'/finish-turn',{method:'POST',body:JSON.stringify({player_id:user.id})});game=x.game.data||x.game;render()}catch(e){showApiError(e)}finally{finishTurnPending=false}}
loadAvailableGames();setInterval(loadAvailableGames,3000);
</script></body></html>
