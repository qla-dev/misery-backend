<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function google(Request $request)
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $googleUser = $this->verifyGoogleIdToken($validated['id_token']);
        $googleId = $googleUser['sub'] ?? null;
        $email = $googleUser['email'] ?? null;

        if (! is_string($googleId) || $googleId === '' || ! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'id_token' => ['Google account did not return a valid ID and email.'],
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();
        $isNewUser = $user === null;

        if ($user?->google_id && $user->google_id !== $googleId) {
            throw ValidationException::withMessages([
                'id_token' => ['This email is already linked to another Google account.'],
            ]);
        }

        if ($user) {
            $user->forceFill(['google_id' => $googleId])->save();
        } else {
            $user = User::query()->create([
                'name' => trim((string) ($googleUser['name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleId,
            ]);
        }

        return $this->authenticatedResponse($user, $isNewUser);
    }

    public function apple(Request $request)
    {
        $validated = $request->validate([
            'identity_token' => ['required', 'string'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $appleUser = $this->verifyAppleIdentityToken($validated['identity_token']);
        $appleId = $appleUser['sub'] ?? null;
        $email = $appleUser['email'] ?? null;

        if (! is_string($appleId) || $appleId === '') {
            throw ValidationException::withMessages([
                'identity_token' => ['Apple account did not return a valid ID.'],
            ]);
        }

        $query = User::query()->where('apple_id', $appleId);
        if (is_string($email) && $email !== '') {
            $query->orWhere('email', $email);
        }

        $user = $query->first();
        $isNewUser = $user === null;

        if ($user?->apple_id && $user->apple_id !== $appleId) {
            throw ValidationException::withMessages([
                'identity_token' => ['This email is already linked to another Apple account.'],
            ]);
        }

        if ($user) {
            $user->forceFill(['apple_id' => $appleId])->save();
        } else {
            if (! is_string($email) || $email === '') {
                throw ValidationException::withMessages([
                    'identity_token' => ['Apple did not return an email for this new account.'],
                ]);
            }

            $user = User::query()->create([
                'name' => trim((string) ($validated['full_name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'apple_id' => $appleId,
            ]);
        }

        return $this->authenticatedResponse($user, $isNewUser);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function authenticatedResponse(User $user, bool $isNewUser)
    {
        return response()->json([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $this->formatUser($user->refresh()),
            'is_new_user' => $isNewUser,
        ]);
    }

    private function formatUser(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'color']);
    }

    private function verifyGoogleIdToken(string $idToken): array
    {
        $clientIds = config('services.google.client_ids', []);

        if ($clientIds === []) {
            throw ValidationException::withMessages([
                'id_token' => ['Google login is not configured.'],
            ]);
        }

        $response = Http::asJson()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (! $response->ok()) {
            throw ValidationException::withMessages(['id_token' => ['Invalid Google token.']]);
        }

        $payload = $response->json();

        if (! in_array($payload['aud'] ?? null, $clientIds, true)) {
            throw ValidationException::withMessages([
                'id_token' => ['Google token was not issued for this app.'],
            ]);
        }

        if (($payload['email_verified'] ?? null) !== true && ($payload['email_verified'] ?? null) !== 'true') {
            throw ValidationException::withMessages(['id_token' => ['Google email is not verified.']]);
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw ValidationException::withMessages(['id_token' => ['Google token has expired.']]);
        }

        return $payload;
    }

    private function verifyAppleIdentityToken(string $identityToken): array
    {
        $clientIds = config('services.apple.client_ids', []);

        if ($clientIds === []) {
            throw ValidationException::withMessages([
                'identity_token' => ['Apple login is not configured.'],
            ]);
        }

        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) {
            throw ValidationException::withMessages(['identity_token' => ['Invalid Apple token.']]);
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        $signature = $this->base64UrlDecode($parts[2]);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'RS256') {
            throw ValidationException::withMessages(['identity_token' => ['Invalid Apple token.']]);
        }

        $keysResponse = Http::asJson()->get('https://appleid.apple.com/auth/keys');
        $key = $keysResponse->ok()
            ? collect($keysResponse->json('keys', []))->first(
                fn (array $candidate) => ($candidate['kid'] ?? null) === ($header['kid'] ?? null)
            )
            : null;

        if (! $key || ! $this->verifyJwtSignature($parts[0].'.'.$parts[1], $signature, $key)) {
            throw ValidationException::withMessages(['identity_token' => ['Invalid Apple token.']]);
        }

        if (($payload['iss'] ?? null) !== 'https://appleid.apple.com'
            || ! in_array($payload['aud'] ?? null, $clientIds, true)
            || ($payload['exp'] ?? 0) < time()) {
            throw ValidationException::withMessages(['identity_token' => ['Invalid or expired Apple token.']]);
        }

        return $payload;
    }

    private function verifyJwtSignature(string $signedPayload, string $signature, array $jwk): bool
    {
        $pem = $this->jwkToPem($jwk);

        return $pem !== null
            && openssl_verify($signedPayload, $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    private function jwkToPem(array $jwk): ?string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            return null;
        }

        $components = $this->asn1Sequence([
            $this->asn1Integer($this->base64UrlDecode($jwk['n'])),
            $this->asn1Integer($this->base64UrlDecode($jwk['e'])),
        ]);
        $publicKey = $this->asn1Sequence([
            $this->asn1Sequence([
                $this->asn1ObjectIdentifier('1.2.840.113549.1.1.1'),
                "\x05\x00",
            ]),
            $this->asn1BitString($components),
        ]);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($publicKey), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4)) ?: '';
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80)) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Sequence(array $items): string
    {
        $value = implode('', $items);

        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        $value = "\x00".$value;

        return "\x03".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1ObjectIdentifier(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $value = chr($parts[0] * 40 + $parts[1]);

        foreach (array_slice($parts, 2) as $part) {
            $encoded = chr($part & 0x7F);
            $part >>= 7;
            while ($part > 0) {
                $encoded = chr(0x80 | ($part & 0x7F)).$encoded;
                $part >>= 7;
            }
            $value .= $encoded;
        }

        return "\x06".$this->asn1Length(strlen($value)).$value;
    }
}
