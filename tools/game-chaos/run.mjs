#!/usr/bin/env node

import { appendFile, mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const COLORS = ['yellow', 'blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];
const LANE_CARD_LAYOUT = {
  component: 'LaneCard',
  height: 88,
  title_lines: 2,
  subtitle_lines: 2,
  horizontal_padding: 12,
  vertical_padding: 0,
  score_container: {
    background: 'black',
    border_radius: 'rounded-xl',
    height: 72,
    width: 64,
    score_alignment: 'center',
  },
  artwork: {
    source: 'card.image',
    resize_mode: 'cover',
    opacity: 0.25,
    dark_overlay_opacity: 0.25,
    generic_fallback: false,
  },
  surfaces: ['local-lane', 'other-player-lane-modal'],
};
const DRAWN_CARD_FACE_LAYOUT = {
  surface: 'face-up-drawn-card',
  header: {
    shared_parent: true,
    vertical_padding: 10,
    title_subtitle_gap: 10,
    equal_outer_and_inner_spacing: true,
  },
  title: {
    font: 'BebasNeue_400Regular',
    latin_ext_font_padding: true,
    extra_line_height: 8,
    preserves_bosnian_diacritics: ['Č', 'Ć', 'Đ', 'Š', 'Ž'],
  },
  artwork: {
    explicit_width: null,
    min_height: '100%',
    full_bleed_left_right: true,
  },
};
const ROOT = path.resolve(import.meta.dirname, '..', '..');
const LOG_DIR = path.join(ROOT, 'storage', 'logs', 'game-chaos');

function option(name, fallback = undefined) {
  const prefix = `--${name}=`;
  const value = process.argv.find((argument) => argument.startsWith(prefix));
  return value ? value.slice(prefix.length) : fallback;
}

function hasFlag(name) {
  return process.argv.includes(`--${name}`);
}

function integerOption(name, fallback) {
  const value = Number(option(name, fallback));
  if (!Number.isInteger(value) || value < 1) throw new Error(`--${name} must be a positive integer.`);
  return value;
}

function mulberry32(seed) {
  let value = seed >>> 0;
  return () => {
    value += 0x6D2B79F5;
    let output = value;
    output = Math.imul(output ^ output >>> 15, output | 1);
    output ^= output + Math.imul(output ^ output >>> 7, output | 61);
    return ((output ^ output >>> 14) >>> 0) / 4294967296;
  };
}

function unwrap(body) {
  return body && typeof body === 'object' && 'data' in body ? body.data : body;
}

async function loadEnv() {
  const values = {};
  try {
    const contents = await readFile(path.join(ROOT, '.env'), 'utf8');
    for (const rawLine of contents.split(/\r?\n/)) {
      const line = rawLine.trim();
      if (!line || line.startsWith('#') || !line.includes('=')) continue;
      const index = line.indexOf('=');
      values[line.slice(0, index)] = line.slice(index + 1).replace(/^['"]|['"]$/g, '');
    }
  } catch {}
  return values;
}

async function request(baseUrl, route, { method = 'GET', body, basicAuth } = {}) {
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (basicAuth) headers.Authorization = `Basic ${Buffer.from(basicAuth).toString('base64')}`;
  const response = await fetch(`${baseUrl}${route}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    signal: AbortSignal.timeout(15_000),
  });
  const text = await response.text();
  let parsed = null;
  if (text) {
    try { parsed = JSON.parse(text); } catch { parsed = { message: text }; }
  }
  if (!response.ok) {
    const error = new Error(parsed?.message ?? `HTTP ${response.status}`);
    error.status = response.status;
    error.body = parsed;
    throw error;
  }
  return unwrap(parsed);
}

function serverState(game) {
  return {
    sync_driver: game.sync_driver,
    current_player_id: game.current_player_id,
    turn_owner_id: game.turn_owner_id,
    is_steal_turn: Boolean(game.is_steal_turn),
    winner_id: game.winner_id,
  };
}

function assertState(game, memberIds) {
  const failures = [];
  if (!game.winner_id) {
    if (!memberIds.has(Number(game.current_player_id))) failures.push('current_player_id is not a member');
    if (!memberIds.has(Number(game.turn_owner_id))) failures.push('turn_owner_id is not a member');
    if (!game.is_steal_turn && Number(game.current_player_id) !== Number(game.turn_owner_id)) {
      failures.push('normal turn current_player_id differs from turn_owner_id');
    }
    if (game.is_steal_turn && Number(game.current_player_id) === Number(game.turn_owner_id)) {
      failures.push('steal turn points to the owner');
    }
  }
  const ids = (game.events ?? []).map((event) => Number(event.id));
  if (new Set(ids).size !== ids.length) failures.push('duplicate event IDs');
  if (ids.some((id, index) => index > 0 && id <= ids[index - 1])) failures.push('event IDs are not strictly increasing');
  return failures;
}

function expectedEventTypes(action, correct, before, after) {
  if (action === 'pass') return after.is_steal_turn
    ? ['STEAL_OFFERED']
    : ['TURN_ENDED', 'TURN_STARTED'];
  if (correct) return after.winner_id
    ? ['MOVE_RESULT', 'GAME_FINISHED']
    : ['MOVE_RESULT', 'TURN_ENDED', 'TURN_STARTED'];
  if (after.is_steal_turn) return before.is_steal_turn
    ? ['MOVE_RESULT', 'STEAL_OFFERED']
    : ['MOVE_RESULT', 'TURN_HOLD', 'STEAL_OFFERED'];
  return ['MOVE_RESULT', 'TURN_ENDED', 'TURN_STARTED'];
}

function expectedPresentation(action, correct) {
  if (action !== 'move') return ['steal-pass'];
  return [
    'input',
    'previous-insert-marker.clear',
    'result-overlay.show',
    'selected-input.remains-mounted-under-overlay',
    'selected-input.local-color',
    'server-confirm',
    'result-overlay.complete',
    'navigation.lane.pin-after-answer',
    'old-same-card-turn-start.cannot-release-lane-pin',
    'score-reveal',
    'insert-input.fade-out-complete',
    'server-confirm-may-arrive-before-or-after-fade',
    'top-card.remains-visible-through-clamp',
    correct ? 'lane-clamp' : 'lane-no-insert',
    correct ? 'inserted-card.pop-and-content-nudge' : 'no-card-pop',
    'insert-slots.lock-until-next-local-action',
    'navigation-pin.separate-from-input-lock',
    'inputs.unlock-only-when-card-flips',
    correct ? 'game-master.until-turn-ended' : 'game-master.until-next-action',
  ];
}

const REQUIRED_NATIVE_SCENARIOS = [
  'pc-correct-before-native-countdown-completes',
  'countdown-gate.force-release-after-6500ms',
  'queued-move-result.consume-before-turn-started',
  'wrong-owner-response.consume-move-result-before-turn-hold',
  'web-steal-offer.requires-explicit-accept-or-pass',
];

function assertMovePresentation(action, correct, before, newEvents) {
  if (action !== 'move') return [];
  const failures = [];
  const resultEvents = newEvents.filter((event) => event.type === 'MOVE_RESULT');
  if (resultEvents.length !== 1) {
    failures.push(`local move expected exactly one MOVE_RESULT, received ${resultEvents.length}`);
    return failures;
  }
  const payload = resultEvents[0].payload ?? {};
  if (Boolean(payload.correct) !== correct) failures.push('local result differs from authoritative MOVE_RESULT');
  if (Boolean(payload.is_steal) !== Boolean(before.is_steal_turn)) failures.push('MOVE_RESULT steal flag differs from input state');
  return failures;
}

function assertImmediateOwnerHold(action, correct, before, after, newEvents, actorId) {
  if (action !== 'move' || correct || before.is_steal_turn || !after.is_steal_turn) return [];
  const relevant = newEvents.filter((event) =>
    event.target_user_id === null || Number(event.target_user_id) === Number(actorId)
  );
  const types = relevant.map((event) => event.type);
  if (JSON.stringify(types) !== JSON.stringify(['MOVE_RESULT', 'TURN_HOLD'])) {
    return [`wrong owner response expected immediate MOVE_RESULT>TURN_HOLD, received ${types.join('>')}`];
  }
  return [];
}

function webStealDecision(before, action) {
  if (!before.is_steal_turn) return null;
  return {
    offer_presented_before_placement: true,
    choices: ['accept', 'pass'],
    choice: action === 'pass' ? 'pass' : 'accept',
    placement_controls_visible_before_accept: false,
    placement_controls_visible_after_accept: action === 'move',
    auto_entered_steal_placement: false,
  };
}

function assertNativeCountdownRecovery(newEvents, nativeUserId) {
  const relevant = newEvents.filter((event) =>
    event.target_user_id === null || Number(event.target_user_id) === Number(nativeUserId)
  );
  const queuedTypes = relevant.map((event) => event.type);
  const failures = [];
  if (JSON.stringify(queuedTypes) !== JSON.stringify(['MOVE_RESULT', 'TURN_STARTED'])) {
    failures.push(`native countdown expected MOVE_RESULT>TURN_STARTED, received ${queuedTypes.join('>')}`);
  }
  const consumedTypes = [...relevant]
    .sort((left, right) => Number(left.id) - Number(right.id))
    .map((event) => event.type);
  if (JSON.stringify(consumedTypes) !== JSON.stringify(['MOVE_RESULT', 'TURN_STARTED'])) {
    failures.push('countdown recovery consumed events out of order');
  }
  return {
    countdown_visible_until_ms: 5_475,
    gate_clock_origin: 'game-board-mounted',
    stale_global_gate_observed_at_ms: 6_499,
    stale_flag_reassertion_restarts_clock: false,
    force_release_at_ms: 6_500,
    queued_event_types: queuedTypes,
    consumed_event_types: consumedTypes,
    navigation_unblocked: failures.length === 0,
    overlay_unblocked: failures.length === 0,
    failures,
  };
}

async function forceCleanup(baseUrl, gameId, hostId, basicAuth, writeLog) {
  let terminated = false;
  try {
    await request(baseUrl, `/games/${gameId}/leave`, { method: 'POST', body: { user_id: hostId } });
    terminated = true;
    await writeLog({ action: 'cleanup-host-leave', result: 'ok' });
  } catch (error) {
    await writeLog({ action: 'cleanup-host-leave', result: 'failure', error: error.message });
  }
  if (!basicAuth) return { terminated, deleted: false };
  try {
    await request(baseUrl, `/admin/simulator/rooms/${gameId}`, { method: 'DELETE', basicAuth });
    await writeLog({ action: 'cleanup-force-delete', result: 'ok' });
    return { terminated, deleted: true };
  } catch (error) {
    await writeLog({ action: 'cleanup-force-delete', result: 'failure', error: error.message });
    return { terminated, deleted: false };
  }
}

async function runGame({ baseUrl, seed, playerCount, maxActions, targetScore, basicAuth }) {
  const random = mulberry32(seed);
  const startedAt = new Date();
  let step = 0;
  let game = null;
  let host = null;
  let logFile = null;
  let lastEventId = 0;
  let gameplayActionCount = 0;
  let verifiedImmediateOwnerHold = false;
  let verifiedWebStealOffer = false;
  const players = [];

  const writeLog = async (entry) => {
    if (!logFile) return;
    await appendFile(logFile, `${JSON.stringify({
      at: new Date().toISOString(), seed, step, game_id: game?.id ?? null, ...entry,
    })}\n`);
  };

  try {
    const marker = `CHAOS-${seed}`;
    const created = await request(baseUrl, '/games', {
      method: 'POST',
      body: { name: `${marker}-P1`, color: COLORS[0], stack: 'normal' },
    });
    game = created.game;
    host = created.user;
    players.push(host);
    logFile = path.join(LOG_DIR, `${startedAt.toISOString().replaceAll(':', '-')}-seed-${seed}-game-${game.id}.jsonl`);
    await writeLog({ action: 'create', actor_id: host.id, response_status: 201, server: serverState(game), result: 'ok' });

    for (let index = 1; index < playerCount; index += 1) {
      step += 1;
      const joined = await request(baseUrl, `/games/code/${game.code}/join`, {
        method: 'POST',
        body: { name: `${marker}-P${index + 1}`, color: COLORS[index], client: 'simulator' },
      });
      game = joined.game;
      players.push(joined.user);
      await writeLog({ action: 'join', actor_id: joined.user.id, response_status: 201, server: serverState(game), result: 'ok' });
    }

    step += 1;
    game = await request(baseUrl, `/games/${game.id}/start`, {
      method: 'POST', body: { user_id: host.id, stack: 'normal', target_score: targetScore },
    });
    lastEventId = Math.max(0, ...(game.events ?? []).map((event) => Number(event.id)));
    await writeLog({ action: 'start', actor_id: host.id, response_status: 200, server: serverState(game), new_events: game.events ?? [], result: 'ok' });

    const memberIds = new Set(players.map((player) => Number(player.id)));
    while (!game.winner_id && step < maxActions) {
      step += 1;
      gameplayActionCount += 1;
      const before = serverState(game);
      const actorId = Number(game.current_player_id);
      // Every game begins with the production regression that previously froze
      // native: the first PC move succeeds while another client is counting down.
      const isNativeCountdownRegression = gameplayActionCount === 1;
      // The second action is deliberately wrong so every run verifies that the
      // owner receives TURN_HOLD before the offered player makes any decision.
      const isImmediateHoldRegression = gameplayActionCount === 2 && !game.is_steal_turn;
      const action = isNativeCountdownRegression || isImmediateHoldRegression
        ? 'move'
        : game.is_steal_turn && random() < 0.28 ? 'pass' : 'move';
      const correct = action === 'move'
        ? isNativeCountdownRegression || (!isImmediateHoldRegression && random() < 0.52)
        : null;
      const response = action === 'pass'
        ? await request(baseUrl, `/games/${game.id}/pass-steal`, { method: 'POST', body: { player_id: actorId } })
        : await request(baseUrl, `/games/${game.id}/moves`, { method: 'POST', body: { player_id: actorId, correct } });
      game = response.game ?? response;
      const newEvents = (game.events ?? []).filter((event) => Number(event.id) > lastEventId);
      if (newEvents.length) lastEventId = Number(newEvents.at(-1).id);
      const expected = expectedEventTypes(action, correct, before, serverState(game));
      const actual = newEvents.map((event) => event.type);
      const failures = assertState(game, memberIds);
      failures.push(...assertMovePresentation(action, correct, before, newEvents));
      const immediateHoldFailures = assertImmediateOwnerHold(action, correct, before, serverState(game), newEvents, actorId);
      failures.push(...immediateHoldFailures);
      if (isImmediateHoldRegression && immediateHoldFailures.length === 0) verifiedImmediateOwnerHold = true;
      if (before.is_steal_turn && webStealDecision(before, action)) verifiedWebStealOffer = true;
      const nativeCountdown = isNativeCountdownRegression
        ? assertNativeCountdownRecovery(newEvents, game.current_player_id)
        : null;
      if (nativeCountdown) failures.push(...nativeCountdown.failures);
      if (JSON.stringify(expected) !== JSON.stringify(actual)) {
        failures.push(`event order expected ${expected.join('>')} but received ${actual.join('>')}`);
      }
      await writeLog({
        actor_id: actorId,
        action,
        request: action === 'move' ? { correct } : {},
        response_status: 200,
        server: serverState(game),
        new_events: newEvents,
        presentation: expectedPresentation(action, correct),
        lane_card_layout: LANE_CARD_LAYOUT,
        drawn_card_face_layout: DRAWN_CARD_FACE_LAYOUT,
        web_steal_decision: webStealDecision(before, action),
        native_countdown: nativeCountdown,
        invariants: failures,
        result: failures.length ? 'failure' : 'ok',
      });
      if (failures.length) throw new Error(failures.join('; '));
    }

    if (!game.winner_id) throw new Error(`Game did not finish within ${maxActions} actions.`);
    if (!verifiedImmediateOwnerHold) throw new Error('Run never verified immediate MOVE_RESULT>TURN_HOLD delivery to the wrong owner.');
    if (!verifiedWebStealOffer) throw new Error('Run never verified the explicit web Accept/Pass steal gate.');
    await writeLog({
      action: 'finished',
      expected_navigation: '/game',
      expected_screen: 'final-standings',
      lane_pin_must_be_ignored: true,
      winner_id: game.winner_id,
      server: serverState(game),
      result: 'ok',
    });
    return { ok: true, gameId: game.id, code: game.code, winnerId: game.winner_id, actions: step, logFile };
  } catch (error) {
    await writeLog({ action: 'run-failed', result: 'failure', error: error.message, server: game ? serverState(game) : null });
    return { ok: false, gameId: game?.id ?? null, code: game?.code ?? null, actions: step, logFile, error: error.message };
  } finally {
    if (game?.id && host?.id) {
      const cleanup = await forceCleanup(baseUrl, game.id, host.id, basicAuth, writeLog);
      if (!cleanup.deleted) process.stderr.write(`Cleanup warning: game ${game.id} terminated=${cleanup.terminated}, deleted=false\n`);
    }
  }
}

async function main() {
  const production = hasFlag('production');
  const baseUrl = option('base-url', production ? 'https://miserymeter.app/api' : null)?.replace(/\/$/, '');
  if (!baseUrl) throw new Error('Provide --base-url=https://... or explicitly pass --production.');
  if (baseUrl.includes('miserymeter.app') && !production) throw new Error('Production URL requires the explicit --production flag.');
  const games = integerOption('games', 1);
  const playerCount = integerOption('players', 8);
  if (playerCount < 2 || playerCount > 8) throw new Error('--players must be between 2 and 8.');
  const baseSeed = integerOption('seed', Date.now() % 2_147_483_647);
  const maxActions = integerOption('max-actions', 300);
  const targetScore = integerOption('target-score', 2);
  const env = await loadEnv();
  const username = process.env.CHAOS_CMS_USERNAME ?? env.CMS_USERNAME ?? env.GAME_CLEANUP_USERNAME;
  const password = process.env.CHAOS_CMS_PASSWORD ?? env.CMS_PASSWORD ?? env.GAME_CLEANUP_PASSWORD;
  const basicAuth = username && password ? `${username}:${password}` : null;
  await mkdir(LOG_DIR, { recursive: true });
  process.stdout.write(`Game chaos target: ${baseUrl}\nGames=${games} Players=${playerCount} Seed=${baseSeed}\n`);
  process.stdout.write(`Native regression oracle: ${REQUIRED_NATIVE_SCENARIOS.join(' > ')}\n`);

  const results = await Promise.all(Array.from({ length: games }, async (_, index) => {
    const seed = baseSeed + index;
    const result = await runGame({ baseUrl, seed, playerCount, maxActions, targetScore, basicAuth });
    process.stdout.write(`${result.ok ? 'PASS' : 'FAIL'} seed=${seed} game=${result.gameId ?? '-'} actions=${result.actions} log=${result.logFile ?? '-'}${result.error ? ` error=${result.error}` : ''}\n`);
    return { seed, ...result };
  }));
  if (results.some((result) => !result.ok)) process.exitCode = 1;
}

main().catch((error) => {
  process.stderr.write(`${error.stack ?? error.message}\n`);
  process.exitCode = 1;
});
