<?php

namespace App\Services;

use Ably\AblyRest;
use App\Models\Game;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class RealtimeTransportAllocator
{
    private const HOSTED_PROVIDERS = ['pusher', 'ably'];

    public function selectForNewRoom(): string
    {
        if ($this->reverbOverrideEnabled()) {
            $this->assertReverbConfigured();

            return 'reverb';
        }

        return $this->firstAvailable(1);
    }

    public function ensureCapacityForJoin(Game $game): string
    {
        if ($this->reverbOverrideEnabled()) {
            $this->assertReverbConfigured();
            if ($game->sync_driver !== 'reverb') {
                $game->update(['sync_driver' => 'reverb']);
            }

            return 'reverb';
        }

        $current = $game->sync_driver ?: 'polling';
        if ($current === 'reverb') {
            return $current;
        }
        if ($current === 'polling') {
            return $current;
        }
        if ($this->providerAvailable($current) && $this->usage($current) + 1 <= $this->capacity($current)) {
            return $current;
        }

        $requiredConnections = $game->members()->count() + 1;
        $next = $this->firstAvailable($requiredConnections, [$current]);
        $game->update(['sync_driver' => $next]);

        return $next;
    }

    public function providerAvailable(string $provider): bool
    {
        if (! in_array($provider, self::HOSTED_PROVIDERS, true) || ! $this->configured($provider)) {
            return false;
        }

        return Cache::remember("realtime-provider-health:{$provider}", 15, function () use ($provider) {
            try {
                if ($provider === 'pusher') {
                    $this->pusher()->get('/channels', [], true);
                } else {
                    $this->ably()->auth->requestToken(['ttl' => 60_000]);
                }

                return true;
            } catch (\Throwable $error) {
                Log::warning('Realtime provider probe failed', ['provider' => $provider, 'message' => $error->getMessage()]);

                return false;
            }
        });
    }

    public function createAblyTokenRequest(Game $game, int $userId): object
    {
        return $this->ably()->auth->createTokenRequest([
            'clientId' => (string) $userId,
            'ttl' => 60 * 60 * 1000,
            'capability' => ["game.{$game->id}" => ['subscribe']],
        ]);
    }

    public function usage(string $provider): int
    {
        $assigned = $this->assignedUsage($provider);

        if ($provider !== 'pusher' || ! $this->configured('pusher')) {
            return $assigned;
        }

        $observed = Cache::remember('realtime-provider-usage:pusher', 10, function () {
            try {
                $response = $this->pusher()->get('/channels', ['info' => 'subscription_count'], true);

                return (int) collect($response['channels'] ?? [])->sum('subscription_count');
            } catch (\Throwable) {
                return 0;
            }
        });

        return max($assigned, (int) $observed);
    }

    public function status(): array
    {
        $strictReverb = $this->reverbOverrideEnabled();
        $pusherConfigured = $this->configured('pusher');
        $ablyConfigured = $this->configured('ably');
        $pusherHealthy = ! $strictReverb && $pusherConfigured ? $this->providerAvailable('pusher') : null;
        $ablyHealthy = ! $strictReverb && $ablyConfigured ? $this->providerAvailable('ably') : null;
        $pusherActive = $strictReverb ? $this->assignedUsage('pusher') : $this->usage('pusher');
        $ablyActive = $this->assignedUsage('ably');
        $pollingActive = $this->assignedUsage('polling');
        $reverbActive = $strictReverb ? $this->allActiveMembers() : $this->assignedUsage('reverb');
        $reverbLimit = config('reverb.apps.apps.0.max_connections');
        $reverbLimit = filled($reverbLimit) ? (int) $reverbLimit : null;

        return [
            'mode' => $strictReverb ? 'reverb_override' : 'launch_fallback',
            'primary_provider' => config('game.realtime_primary_provider'),
            'generated_at' => now()->toISOString(),
            'providers' => [
                'pusher' => $this->providerStatus('pusher', $pusherActive, $this->capacity('pusher'), $pusherConfigured, $pusherHealthy, $strictReverb),
                'ably' => $this->providerStatus('ably', $ablyActive, $this->capacity('ably'), $ablyConfigured, $ablyHealthy, $strictReverb),
                'polling' => [
                    'active_players' => $pollingActive,
                    'limit' => null,
                    'remaining' => null,
                    'configured' => true,
                    'healthy' => true,
                    'state' => $strictReverb ? 'bypassed' : 'available',
                ],
                'reverb' => [
                    'active_players' => $reverbActive,
                    'limit' => $reverbLimit,
                    'remaining' => $reverbLimit === null ? null : max(0, $reverbLimit - $reverbActive),
                    'configured' => $this->reverbConfigured(),
                    'healthy' => null,
                    'state' => $strictReverb
                        ? ($this->reverbConfigured() ? 'active' : 'misconfigured')
                        : ($reverbActive > 0 ? 'active' : 'standby'),
                ],
            ],
        ];
    }

    private function firstAvailable(int $requiredConnections, array $excluded = []): string
    {
        foreach ($this->hostedProviders() as $provider) {
            if (in_array($provider, $excluded, true)) {
                continue;
            }
            if (! $this->providerAvailable($provider)) {
                continue;
            }
            if ($this->usage($provider) + $requiredConnections <= $this->capacity($provider)) {
                return $provider;
            }
        }

        return 'polling';
    }

    private function hostedProviders(): array
    {
        $primary = config('game.realtime_primary_provider') === 'ably' ? 'ably' : 'pusher';

        return [$primary, $primary === 'pusher' ? 'ably' : 'pusher'];
    }

    private function reverbOverrideEnabled(): bool
    {
        return (bool) config('game.reverb_override', false);
    }

    private function assertReverbConfigured(): void
    {
        if (! $this->reverbConfigured()) {
            throw new \RuntimeException('REVERB_OVERRIDE is enabled, but the Reverb app ID, key, secret, or host is missing.');
        }
    }

    private function reverbConfigured(): bool
    {
        $config = config('broadcasting.connections.reverb');

        return filled($config['app_id'] ?? null)
            && filled($config['key'] ?? null)
            && filled($config['secret'] ?? null)
            && filled($config['options']['host'] ?? null);
    }

    private function assignedUsage(string $provider): int
    {
        return (int) DB::table('members')
            ->join('games', 'games.id', '=', 'members.game_id')
            ->where('games.sync_driver', $provider)
            ->whereNull('games.terminated_at')
            ->where('members.updated_at', '>=', now()->subSeconds(config('game.member_inactivity_timeout_seconds')))
            ->count();
    }

    private function allActiveMembers(): int
    {
        return (int) DB::table('members')
            ->join('games', 'games.id', '=', 'members.game_id')
            ->whereNull('games.terminated_at')
            ->where('members.updated_at', '>=', now()->subSeconds(config('game.member_inactivity_timeout_seconds')))
            ->count();
    }

    private function providerStatus(string $provider, int $active, int $limit, bool $configured, ?bool $healthy, bool $bypassed): array
    {
        $state = $bypassed
            ? 'bypassed'
            : (! $configured ? 'unconfigured' : ($healthy === false ? 'unavailable' : ($active >= $limit ? 'full' : 'available')));

        return [
            'active_players' => $active,
            'limit' => $limit,
            'remaining' => max(0, $limit - $active),
            'configured' => $configured,
            'healthy' => $healthy,
            'state' => $state,
            'primary' => config('game.realtime_primary_provider') === $provider,
        ];
    }

    private function configured(string $provider): bool
    {
        return $provider === 'pusher'
            ? filled(config('broadcasting.connections.pusher.app_id'))
                && filled(config('broadcasting.connections.pusher.key'))
                && filled(config('broadcasting.connections.pusher.secret'))
            : filled(config('broadcasting.connections.ably.key'));
    }

    private function capacity(string $provider): int
    {
        return (int) config("game.{$provider}_connection_capacity");
    }

    private function pusher(): Pusher
    {
        $config = config('broadcasting.connections.pusher');

        return new Pusher($config['key'], $config['secret'], $config['app_id'], [
            ...$config['options'],
            'timeout' => config('game.provider_probe_timeout_ms') / 1000,
        ]);
    }

    private function ably(): AblyRest
    {
        return new AblyRest([
            'key' => config('broadcasting.connections.ably.key'),
            'httpRequestTimeout' => config('game.provider_probe_timeout_ms'),
        ]);
    }
}
