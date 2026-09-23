<?php

namespace GraphMail\LaravelGraphMailLegacy\Services;

use GraphMail\LaravelGraphMailLegacy\Exceptions\GraphMailException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MicrosoftGraphTokenService
{
    /**
     * Get a cached (or freshly acquired) app-only access token via the
     * OAuth2 client credentials flow.
     *
     * @return string
     * @throws GraphMailException
     */
    public function getAccessToken()
    {
        $cacheKey = config('graph-mail.token_cache_key');
        $ttl = (int) config('graph-mail.token_cache_ttl');

        return Cache::remember($cacheKey, $ttl, function () {
            $tenantId = config('graph-mail.tenant_id');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => config('graph-mail.client_id'),
                    'client_secret' => config('graph-mail.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->failed()) {
                throw GraphMailException::tokenRequestFailed($response->body());
            }

            $token = $response->json('access_token');

            if (empty($token)) {
                throw GraphMailException::tokenRequestFailed($response->body());
            }

            return $token;
        });
    }

    /**
     * Forget the cached token, forcing a fresh one on next request.
     * Useful after rotating a client secret or when debugging.
     *
     * @return void
     */
    public function forgetToken()
    {
        Cache::forget(config('graph-mail.token_cache_key'));
    }
}
