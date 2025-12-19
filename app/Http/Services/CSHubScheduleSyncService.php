<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class CSHubScheduleSyncService
{
    public function sync(array $payload): void
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');

        if (!$baseUrl || !$user || !$password) {
            throw new Exception("CS Hub sync is not configured properly.");
        }

        $endpoint = rtrim($baseUrl, '/') . '/sync-schedule';

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json",
            ])
            ->timeout(30)
            ->retry(3, 2000)
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new Exception("CS Hub sync failed: " . $message);
        }
    }
}
