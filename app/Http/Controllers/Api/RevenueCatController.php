<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RevenueCatController extends Controller
{
    private const PRODUCTS = [
        'misery_monthly' => 'monthly',
        'misery_yearly' => 'yearly',
    ];

    public function syncPro(Request $request)
    {
        $user = $request->user();
        $secretApiKey = config('services.revenuecat.secret_api_key');

        if (!$secretApiKey) {
            return response()->json(['message' => 'RevenueCat secret API key is not configured.'], 503);
        }

        $appUserId = "misery-user-{$user->id}";
        $response = Http::withToken($secretApiKey)
            ->acceptJson()
            ->timeout(8)
            ->get('https://api.revenuecat.com/v1/subscribers/'.rawurlencode($appUserId));

        if (!$response->successful()) {
            return response()->json(['message' => 'RevenueCat verification failed.'], 502);
        }

        [$entitlementId, $entitlement] = $this->activeProEntitlement(
            $response->json('subscriber.entitlements') ?: []
        );

        if (!$entitlement) {
            $user->forceFill([
                'pro_status' => 'inactive',
                'pro_started_at' => null,
                'pro_ends_at' => null,
                'revenuecat_product_id' => null,
                'revenuecat_entitlement_id' => null,
            ])->save();
        } else {
            $productId = (string) $entitlement['product_identifier'];
            $user->forceFill([
                'pro_status' => self::PRODUCTS[$productId],
                'pro_started_at' => $entitlement['purchase_date'] ?? null,
                'pro_ends_at' => $entitlement['expires_date'] ?? null,
                'revenuecat_product_id' => $productId,
                'revenuecat_entitlement_id' => $entitlementId,
            ])->save();
        }

        return response()->json([
            'data' => [
                'active' => $entitlement !== null,
                'app_user_id' => $appUserId,
                'user' => $this->formatUser($user->refresh()),
            ],
        ]);
    }

    private function activeProEntitlement(array $entitlements): array
    {
        $preferredId = config('services.revenuecat.pro_entitlement_id');
        if ($preferredId && isset($entitlements[$preferredId]) && $this->isActivePro($entitlements[$preferredId])) {
            return [$preferredId, $entitlements[$preferredId]];
        }

        foreach ($entitlements as $id => $entitlement) {
            if (is_array($entitlement) && $this->isActivePro($entitlement)) {
                return [(string) $id, $entitlement];
            }
        }

        return [null, null];
    }

    private function isActivePro(array $entitlement): bool
    {
        $productId = (string) ($entitlement['product_identifier'] ?? '');
        if (!isset(self::PRODUCTS[$productId])) return false;
        $expiresAt = $entitlement['expires_date'] ?? null;

        return !$expiresAt || now()->lt($expiresAt);
    }

    private function formatUser($user): array
    {
        return $user->only([
            'id', 'name', 'email', 'color', 'pro_status', 'pro_started_at', 'pro_ends_at',
            'revenuecat_product_id', 'revenuecat_entitlement_id',
        ]);
    }
}
