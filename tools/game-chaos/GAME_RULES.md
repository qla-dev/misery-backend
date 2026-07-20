# Misery Index — authoritative game rules and chaos-test oracle

This file is the source of truth for automated game simulation. Future agents must read it before changing or running the chaos simulator.

## Purpose

The simulator creates real games through the production API, drives up to eight players, records every request, response and server event, and verifies that gameplay can never stall or present events out of order.

Production chaos users and rooms must use names beginning with `CHAOS-`. Every run must have a reproducible integer seed. Logs must be written before cleanup is attempted.

## Players and room

- A game supports 2–8 players. The primary stress configuration uses 8.
- Player order is the order returned in `game.members`.
- The host creates and starts the room.
- A game cannot start without at least two members.
- The target is the number of cards earned after the three starting lane cards.
- The first three lane cards are worth zero progress.
- A player wins immediately when `owned_card_count - 3 >= target_score`.
- A chaos run must use the real HTTP API. It must not modify gameplay tables directly.

## Card placement

- Each lane is sorted by card score.
- A slot is correct when the current card score is between the previous and next card scores, inclusive.
- The client submits one boolean `correct` value to `POST /api/games/{id}/moves`.
- The client can determine correct/wrong from the selected slot and must open the local result overlay immediately on input.
- The server response and its `MOVE_RESULT` event remain authoritative for score, ownership and turn progression.
- The matching server `MOVE_RESULT` confirms the already visible local overlay; it must never open a duplicate overlay.

### Local placement presentation

Every local normal or steal placement uses this exact order:

1. The player presses a lane slot.
2. The correct/wrong/steal result overlay opens immediately, before waiting for the move API.
3. The top card remains present; input must not remove it or insert it into the lane yet.
   A local or authoritative `success` flag alone must never hide it; only matching `lastInsertedCardId` may remove the top copy.
4. `MOVE_RESULT` confirms the local result in the background and is deduplicated against the visible overlay.
5. The result overlay completes.
6. Because a placed card is already flipped, navigation goes directly to Lane; Card must never flash first.
7. The card score is revealed.
8. For a correct normal move or successful steal, the card is inserted with the lane clamp/insert animation.
9. For a wrong move, the card is not inserted.

Before a new placement overlay opens, the previous `lastInsertedCardId` animation marker must be cleared. After the overlay, the marker may target only the card being inserted and must expire after the animation. A previous lane card must never replay the insertion animation when Lane remounts.

### Lane clamp animation

The clamp is a strict two-stage animation:

1. The entire selected `INSERT HERE` control fades to zero and scales down slightly.
2. Only after that fade completion may the input be removed and the correct card be inserted.
3. The inserted card pops in at the selected slot with a short spring (small upward offset, subtle overshoot and rotation correction).
4. Existing lane content shifts/nudges smoothly to make space in the same layout transition.

The card must never mount while the input is still fading, and no existing/first card may receive the new-card pop animation.

On slot tap, remove every other `INSERT HERE` control but keep the clicked control continuously mounted beneath the result overlay. Do not hide it and recreate it after the overlay. Once the overlay is gone and the authoritative result is known, that same control owns the fade animation. Its real `onFadeComplete` is the only trigger for the following transition: correct results clamp the card; wrong results remove the faded input without clamping. A parallel GameBoard timeout must not imitate or race the UI fade completion.

The retained input keeps the exact same dimensions, padding, radius and border width before, during and after the overlay. Once the result is known, only its color and subtle background change: green for correct and red for wrong. Correct then clamps; wrong does not.

Ordinary moves and accepted steal attempts use this exact same retained-input lifecycle. The selected input receives its locally known green/red color immediately beneath the result overlay. Overlay completion unlocks its fade immediately; it must never wait for `MOVE_RESULT`. The authoritative result controls only the eventual correct-card clamp. If confirmation arrives after fade, clamp when it arrives without remounting the input; if it arrives during fade, clamp when fade completes.

A queued or completed `TURN_STARTED` must not unlock or reshow lane inputs. Accepted-steal notice must not unlock them either. The previous ordinary/steal placement remains locked through fade and clamp; inputs unlock only at the instant the new actionable card is successfully flipped. Successful steal placement follows the ordinary-correct path, and failed steal placement follows the ordinary-wrong path with no separate legacy lane behavior.

Navigation pinning and lane-input locking are separate state. A local result remains on Lane through overlay/fade/clamp. A new ordinary turn or accepted steal attempt returns to Card, while lane inputs remain locked; only flipping that actionable card unlocks them.

During correct ordinary or steal clamping, never hide the top drawn-card presentation. Adding the card to Lane creates its lane copy but does not replace or remove the top copy. The top presentation changes only when authoritative state supplies the next drawn card or navigation leaves Lane.

After overlay completion, the retained input fades out in 120ms without waiting for the server.

The selected input is one continuous mounted component from the original tap through the result overlay. Other inputs lock immediately, but the chosen input remains underneath the overlay and fades only after the overlay completes. It must never disappear on tap, remount/reappear after the overlay, and then fade.

There is no applause sound for an ordinary correct placement. Applause is reserved for winning the game.

## Normal turn

At the start of a normal turn:

- `current_player_id` and `turn_owner_id` are the same player.
- `is_steal_turn` is false.
- The target player receives `TURN_STARTED`.
- The player may flip and place the current card only after the start presentation is complete.

### Correct normal move

Required server event order:

1. `MOVE_RESULT` with `correct=true`, `is_steal=false`.
2. If the target score is reached: `GAME_FINISHED`.
3. Otherwise: `TURN_ENDED`, targeted to the previous owner.
4. Then `TURN_STARTED`, targeted to the next owner.

There is no steal offer after a correct move.

### Wrong normal move

Required server event order:

1. `MOVE_RESULT` with `correct=false`, `is_steal=false`.
2. `TURN_HOLD`, targeted to the owner whose card is being offered.
3. `STEAL_OFFERED`, targeted to the next eligible player.

The round does not end while another eligible player can attempt the steal.

## Steal decision

- `STEAL_OFFERED` starts the decision timeout immediately at the event's server timestamp.
- The player must receive an explicit Accept/Pass choice.
- Native and web clients follow the same gate: neither may enter steal-placement mode automatically.
- On web, Correct/Wrong placement controls remain hidden until the targeted player explicitly chooses `Try to steal`; choosing `Pass` calls the pass endpoint immediately.
- The card must not display `TAP TO FLIP TO TRY TO STEAL` before Accept.
- Accept makes the same current card available to that stealer.
- Pass calls `POST /api/games/{id}/pass-steal` and does not create a `MOVE_RESULT`.
- A temporary inactivity warning may cover the decision, but it must not complete, discard or duplicate `STEAL_OFFERED`.
- Closing an inactivity warning must reveal the same pending Accept/Pass decision again.

### Correct steal

Required server event order:

1. `MOVE_RESULT` with `correct=true`, `is_steal=true`.
2. If the target score is reached: `GAME_FINISHED`.
3. Otherwise: `TURN_ENDED`, targeted to the original turn owner.
4. Then `TURN_STARTED`, targeted to the player after the original owner.

The stolen card is added to the successful stealer's lane.

### Wrong steal

If another eligible player remains:

1. `MOVE_RESULT` with `correct=false`, `is_steal=true`.
2. `STEAL_OFFERED`, targeted to the next eligible player.

If every eligible non-owner player has attempted or passed:

1. `MOVE_RESULT` with `correct=false`, `is_steal=true`.
2. `TURN_ENDED`, targeted to the original owner.
3. `TURN_STARTED`, targeted to the player after the original owner.

The owner is never offered a steal of their own card.

## Pass sequence

- Passing advances to the next eligible non-owner player.
- If another stealer exists, the only new actionable event is `STEAL_OFFERED` for that player.
- If no stealer remains, the server emits `TURN_ENDED` and then `TURN_STARTED`.
- A player may receive at most one offer for the same card in one round.

## Autonomous CMS bots

- The CMS bot action is available only for an open, unterminated lobby containing exactly one human and no existing bots.
- The operator chooses 1–7 bots. They become real `members` with persistent `users.is_bot = true` and unique random names from the combined Bosnian/Balkan and US public-room name pool.
- When two or more bots are added together, at least one name comes from the Bosnian/Balkan set and at least one comes from the US set; remaining names are random across the combined pool.
- Adding bots immediately broadcasts `bots.added` over the room's assigned realtime transport so an open Pusher lobby sees them without a game `GET`.
- Bot count is validated server-side and may not exceed the seven available seats in a one-human lobby.
- Each bot waits a configurable randomized thinking interval after its card becomes current before acting; the default is 3–6 seconds.
- Server-room bots are controlled only by the backend queue; native, web and simulator clients must never submit moves for them.
- A bot acts only while it is the authoritative `current_player_id`. A per-game lock prevents duplicate bot turns.
- Bot decisions match chaos probabilities: pass 28% of steal offers; otherwise attempt a move that is correct 52% of the time.
- Every bot action uses the production `move` or `passSteal` controller path, so event order, steal progression, scoring, victory and realtime broadcasts remain identical to human actions.
- A bot in a Pusher-assigned room publishes `move.created` or `steal.passed` through that same Pusher game channel.
- Bots are excluded from presence inactivity removal and remain ready for a rematch.
- When the next actor is human, the bot chain stops and waits for that human's real server action.

## Lobby and inactivity notices

- While Public Games is already open, a newly appearing room plays the same arrival sound as a newly joined lobby player. Initial hydration and rooms prefetched before opening Public Games remain silent.
- The Bosnian inactivity title is exactly two rows: `TVOJ POTEZ` / `ČEKA`.
- The inactivity title uses the same shared 10 px circle-to-title gap as every other `LaneModal` title. A two-row title must not add its own outer top margin.
- The final inactivity values `3`, `2`, `1` are a first-class `kick-countdown` action inside `GameActionQueue`, not an independent overlay mounted by the game layout.
- `kick-countdown` preempts ordinary inactivity and gameplay notices, but a terminal room-exit action remains highest priority.

## Synthetic public listings

- After stale-room cleanup, the server maintains at least `MINIMUM_PUBLIC_ROOM_LISTINGS` active public list entries (default 10).
- It fills only the deficit with synthetic in-progress listings and removes surplus synthetic listings as real active rooms increase.
- Synthetic listings have fake host names, fake player counts, randomized ages, `started = true`, and `sync_driver = polling`.
- They have no `members`, no player sessions, no heartbeat, no realtime token, and no Pusher/Ably/Reverb allocation or connection.
- They render with the existing red `IN PROGRESS` duration and never show a Join action.
- Cleanup must exclude synthetic listings from stale started-game deletion; only the balancing step creates or deletes them.

## Presentation order

Each client processes relevant server events strictly by ascending event ID.

- There is exactly one current gameplay event.
- A current event remains current until its presentation explicitly completes.
- Receiving more events must never replace the current event.
- Realtime and polling copies of the same event ID are duplicates and must be ignored.
- `MOVE_RESULT` presentation must complete before `STEAL_OFFERED`, `TURN_STARTED` or `GAME_FINISHED` is presented.
- After the `GAME_FINISHED` notice, every tab and every lane-pinned state must route to `/game` and render final standings. A previous lane pin must never override finished-game navigation.
- A locally predicted result overlay and its matching `MOVE_RESULT` are one presentation, never two.
- Local input must produce `overlay.show` before `server-confirm`, then `overlay.complete`, `score-reveal`, and finally `lane-clamp` for a correct result.
- No event may remain queued indefinitely after the preceding presentation completes.

Expected client lifecycle:

```text
server-events.queued
server-event.consume
server-event.complete
server-event.consume
```

For the client that submitted a move, the committed event stream in the move response is authoritative for presentation and is ingested immediately. A wrong normal move must queue `MOVE_RESULT` and targeted `TURN_HOLD` from that response; the owner must not wait for a later poll, realtime refresh, or another player's Accept action before seeing `YOU'RE ON HOLD`.

## Face-up drawn card

- The title and subtitle share one header parent.
- Header top padding, title/subtitle gap, and header bottom padding are all exactly 10 px.
- Do not add an independent top margin that makes the visible top and bottom spacing unequal.
- The Bebas title keeps native font padding and 8 px of extra line height so `Č`, `Ć`, `Đ`, `Š`, and `Ž` remain intact.
- The artwork has no explicit `width: 100%`; it uses `minHeight: 100%` and stretches full-bleed to the left and right edges of the face-up card.

## Card footer and interaction

- While waiting for an authoritative state: show `GAME MASTER` and a spinner.
- If the server has advanced to another player but the local `TURN_ENDED`/finish presentation has not completed, the footer must still show `GAME MASTER` and a spinner. It must not show `{PLAYER} IS PLAYING` early.
- During a turn-start presentation: do not show `TAP TO FLIP` in the card footer.
- After a normal start presentation: show `TAP TO FLIP`.
- Before accepting a steal: do not show a flip instruction and do not allow flipping.
- After accepting a steal: show `TAP TO FLIP TO TRY TO STEAL`.
- A visually dismissed overlay must not leave an invisible touch-blocking layer.
- After any gameplay notification, navigate directly to Lane when the current card is already flipped.
- Navigate to Card after a gameplay notification only when the current card has not been flipped.
- Never navigate Card and then immediately Lane for the same notification.
- After the local player answers on Lane, pin navigation to Lane through the result, score/clamp, turn-ended, hold and subsequent notification sequence. Do not redirect to Card at any point in that completed-answer flow.
- Release the Lane pin only when the local player receives a new turn or accepts a steal that requires flipping a new/unflipped card.
- A delayed, resumed or duplicate `TURN_STARTED` for the same card that was just answered is not a new turn and must not release the Lane pin or redirect to Card. Only a `TURN_STARTED` carrying a different card ID may release it.
- Once a lane slot is selected, all `INSERT HERE` controls remain disabled through overlay, reveal, clamp and turn transition. They may reappear only for a new local turn/accepted steal; clamping the current card must never re-enable them.
- Every Lane card has a compact fixed height of 88 px, including the retained top pending/revealed card.
- Every Lane card reserves at most two lines for title and two lines for subtitle. Overflow uses a trailing ellipsis; content length must never change card height.
- Known/revealed Lane cards show the concrete card's `card.image` WebP artwork above the score, moving the score lower within its fixed left column. Never use `illustrationType=general_misery` or another generic fallback there. If no image exists, show no artwork. Unknown score cards keep `?.??` centered without revealed artwork.
- Artwork and score share one fixed black container using the same `rounded-xl` corner radius as the Lane card; the unknown `?.??` state uses that same container.
- When concrete artwork exists, it fills that score container as a cover background with a dark readability overlay; the score sits centered over it. Artwork is not rendered as a separate icon above the score.
- The score is centered horizontally and vertically over the artwork background. Artwork opacity is 0.25 and its dark overlay opacity is 0.25.
- Local Lane and other-player Lane modal must render the same shared `LaneCard` component. Copying the design into separate components is forbidden because the two surfaces must never drift.
- The shared card contract is: height 88 px, horizontal padding 12 px, vertical padding 0, score container 64x72 px, black background, `rounded-xl`, concrete `card.image` cover artwork, and 2 title + 2 subtitle lines.

## Countdown and late join to an active game

- Server events may occur while a native client is still showing its initial countdown.
- Events received during countdown are retained in event-ID order.
- The visual countdown lasts 5.475 seconds. A stale global countdown flag must be force-released 6.5 seconds after the GameBoard first mounts so queued events can never remain blocked indefinitely.
- The 6.5-second clock is absolute per game. If Lobby reasserts `isGameCountingDown=true` after that deadline, release it immediately with zero additional delay; reassertion must never restart the clock.
- Unmounting the countdown-owning Card screen clears the global countdown gate.
- After countdown, the client presents all still-relevant events in order.
- A wrong move made during countdown must produce result presentation before the steal decision.
- A correct move made during countdown must produce result presentation before the next turn notice.
- Required late-start regression: another player answers correctly before native countdown completes; native must consume `MOVE_RESULT` and then `TURN_STARTED` immediately after countdown/fail-safe, with working overlays and navigation.

## Inactivity

- The active player's timeout is 60 seconds.
- Warnings occur at 15, 30 and 45 seconds.
- For steal decisions, the deadline starts at `STEAL_OFFERED.created_at`, not when Accept/Pass becomes visible.
- Warnings are temporary UI layers and do not advance the gameplay event machine.
- At expiry, the active guest is removed; expiry of the host terminates the game.
- A stale timeout request must not remove a player after the server turn has advanced.

## Winner and finish

- A winning move always presents its `MOVE_RESULT` first.
- `GAME_FINISHED` is presented only after the winning result completes.
- Multiplayer losers see the final leaderboard, not the solo lives-depleted screen.
- No steal may be offered after a winning move.

## Replay

- Replay returns willing players to the same room lobby.
- Players who have not selected replay must not appear ready merely because another player returned.
- New players may join the replay lobby when seats are available.
- Starting the replay clears old moves, cards and game events.
- The first event of the new game is one `TURN_STARTED` for its first player.

## Disconnect and reconciliation

- UI animations never determine server state.
- A failed or slow request must not freeze navigation or touches.
- After the one initial room bootstrap, every game/lobby update is applied directly from the Pusher, Ably or Reverb `game.updated` payload. A realtime callback must never trigger a game or snapshot `GET`.
- The realtime payload is authoritative and contains compact game state, current card, member IDs, hand card IDs, unseen ordered gameplay events, and the newly created chat message when relevant.
- Expo web uses `pusher-js` for Pusher rooms; it must not silently fall back to polling merely because the client is running in a browser.
- While realtime is connected there is no periodic 30-second safety `GET`.
- Presence and missed-event recovery use `POST /api/games/{id}/heartbeat` with `after_event_id`. Its response contains current compact state and every event after that cursor.
- App resume uses the heartbeat `POST` response. It must not issue a game `GET` while realtime is connected.
- A game `GET` is allowed only for initial bootstrap or explicit polling fallback when the realtime connection cannot be established.
- The Pusher event body must remain below Pusher's 10 KB limit. Recovery heartbeat responses are not constrained by that event limit.
- Realtime state updates players, lanes, current card and winner; it must not invent presentation events.
- A client reconnecting during a steal decision must recover the current `STEAL_OFFERED` exactly once.

## Chaos actions

For every actionable state, the seeded simulator may choose:

- correct placement;
- wrong placement;
- accept steal;
- pass steal;
- response delay;
- burst several legal moves before another simulated client polls;
- duplicate snapshot retrieval;
- pause a client for several seconds;
- reconnect a client;
- change simulated tab;
- allow inactivity warnings or timeout.

The simulator must never send an action that the current server state makes illegal unless the scenario explicitly tests rejection. Expected rejections must be labeled separately from invariant failures.

### Mandatory native countdown regression

Every chaos game must begin with a forced correct PC move while the next simulated native client is still inside its initial countdown. The simulator must retain the relevant broadcast `MOVE_RESULT` and targeted `TURN_STARTED`, deliberately keep a stale global countdown gate through 6.499 seconds, force-release it at 6.5 seconds, and then verify:

- no gameplay event was discarded while gated;
- `MOVE_RESULT` is consumed before `TURN_STARTED`;
- overlay presentation becomes available;
- navigation becomes available;
- the client cannot remain stale after the fail-safe.
- reasserting the stale flag uses the original GameBoard mount epoch and adds zero delay after the deadline.

The action's JSONL entry must include a `native_countdown` object containing countdown duration, force-release time, queued types, consumed types, overlay/navigation status and failures. Merely printing the scenario name is not a test.

## Invariants checked after every action

- Exactly one current player exists while an unfinished game is active.
- `turn_owner_id` is a room member.
- `current_player_id` is a room member.
- `is_steal_turn=false` implies `current_player_id == turn_owner_id`.
- `is_steal_turn=true` implies `current_player_id != turn_owner_id`.
- Event IDs are strictly increasing and unique.
- Every `MOVE_RESULT.move_id` exists once.
- A correct move is never followed by `STEAL_OFFERED` for that card.
- A wrong normal move with eligible players is followed by `TURN_HOLD` and `STEAL_OFFERED`.
- The wrong owner receives `MOVE_RESULT` then `TURN_HOLD` in the same move response, before any stealer action.
- Every simulated web steal turn records an explicit Accept/Pass decision; placement is never available before Accept.
- A successful steal changes card ownership to the stealer.
- A finished game has exactly one winner and no further actionable events.
- No simulated client has a queued event with no current presentation while otherwise idle.
- No client presents a later event before completing an earlier relevant event.
- A local move has exactly one result overlay.
- A local card remains outside the lane until that overlay completes.
- A correct local move clamps the card into the lane only after score reveal; a wrong move never inserts it.
- Every heartbeat recovery response has realtime payload version 1, matches the authoritative POST response state, and includes every new event after the supplied cursor in ascending order.
- Normal simulated gameplay performs no game/snapshot GET; state advances through mutation responses plus realtime-format heartbeat POST recovery.

Any invariant failure fails the run immediately and saves the complete log.

## Log format

Each run writes one JSONL file under `storage/logs/game-chaos/` named:

```text
{UTC timestamp}-seed-{seed}-game-{gameId}.jsonl
```

Every line contains:

```json
{
  "at": "ISO-8601 timestamp",
  "seed": 123,
  "step": 17,
  "game_id": 999,
  "actor_id": 1001,
  "action": "move|pass|snapshot|pause|reconnect|cleanup",
  "request": {},
  "response_status": 200,
  "server": {
    "current_player_id": 1002,
    "turn_owner_id": 1002,
    "is_steal_turn": false,
    "winner_id": null
  },
  "new_events": [],
  "native_countdown": {
    "countdown_visible_until_ms": 5475,
    "force_release_at_ms": 6500,
    "queued_event_types": ["MOVE_RESULT", "TURN_STARTED"],
    "consumed_event_types": ["MOVE_RESULT", "TURN_STARTED"],
    "navigation_unblocked": true,
    "overlay_unblocked": true,
    "failures": []
  },
  "invariants": [],
  "result": "ok|expected-rejection|failure"
}
```

Never write API secrets, CMS credentials, tokens or full environment variables to a log.

## Production safety and cleanup

- Production execution requires an explicit `--production` flag.
- The command must print the target API URL before creating data.
- Production rooms and users must begin with `CHAOS-{seed}-`.
- Default target score should be small enough to finish quickly.
- The complete log must be flushed before cleanup.
- Cleanup should terminate the room through the host leave API, then use the authorized simulator cleanup endpoint when credentials are available.
- Cleanup failure must be recorded prominently with the game ID and room code.
- The runner must support cleanup-only mode by game ID.
- Never run against an unconfirmed URL or use direct destructive SQL against production.

## Minimum acceptance run

Before gameplay changes are accepted:

- 100 seeded games with 8 players;
- at least 25 all-correct rounds;
- at least 25 wrong-owner steal chains;
- at least 25 pass chains;
- at least 10 successful steals;
- at least 10 reconnect/batched-event scenarios;
- zero invariant failures;
- every created production room cleaned or explicitly reported.
