<?php

namespace GraphMail\LaravelGraphMailLegacy\Tests;

use GraphMail\LaravelGraphMailLegacy\GraphMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            GraphMailServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('graph-mail.tenant_id', 'fake-tenant-id');
        $app['config']->set('graph-mail.client_id', 'fake-client-id');
        $app['config']->set('graph-mail.client_secret', 'fake-client-secret');
        $app['config']->set('graph-mail.default_sender', 'sender@example.com');
        $app['config']->set('graph-mail.token_cache_key', 'ms_graph_token_test');

        $app['config']->set('mail.driver', 'graph');
        $app['config']->set('mail.mailers.graph', ['transport' => 'graph']);

        $app['config']->set('cache.default', 'array');
    }
}
