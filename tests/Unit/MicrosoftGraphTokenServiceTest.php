<?php

namespace GraphMail\LaravelGraphMailLegacy\Tests\Unit;

use GraphMail\LaravelGraphMailLegacy\Exceptions\GraphMailException;
use GraphMail\LaravelGraphMailLegacy\Services\MicrosoftGraphTokenService;
use GraphMail\LaravelGraphMailLegacy\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MicrosoftGraphTokenServiceTest extends TestCase
{
    /** @test */
    public function it_acquires_and_caches_an_access_token()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token-123'], 200),
        ]);

        $service = $this->app->make(MicrosoftGraphTokenService::class);

        $token = $service->getAccessToken();

        $this->assertSame('fake-token-123', $token);

        Http::assertSentCount(1);

        // Second call should hit the cache, not fire another request.
        $service->getAccessToken();
        Http::assertSentCount(1);
    }

    /** @test */
    public function it_throws_a_graph_mail_exception_when_the_token_request_fails()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client secret provided.',
            ], 401),
        ]);

        $service = $this->app->make(MicrosoftGraphTokenService::class);

        $this->expectException(GraphMailException::class);

        $service->getAccessToken();
    }

    /** @test */
    public function it_forgets_the_cached_token()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token-123'], 200),
        ]);

        $service = $this->app->make(MicrosoftGraphTokenService::class);
        $service->getAccessToken();

        $service->forgetToken();

        $this->assertFalse(Cache::has('ms_graph_token_test'));
    }
}
