<?php

namespace GraphMail\LaravelGraphMailLegacy;

use GraphMail\LaravelGraphMailLegacy\Console\TestGraphMailCommand;
use GraphMail\LaravelGraphMailLegacy\Services\MicrosoftGraphTokenService;
use GraphMail\LaravelGraphMailLegacy\Transport\GraphTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class GraphMailServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/graph-mail.php', 'graph-mail');

        $this->app->singleton(MicrosoftGraphTokenService::class);
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/graph-mail.php' => config_path('graph-mail.php'),
            ], 'graph-mail-config');

            $this->commands([
                TestGraphMailCommand::class,
            ]);
        }

        Mail::extend('graph', function () {
            return new GraphTransport(
                $this->app->make(MicrosoftGraphTokenService::class),
                config('graph-mail.default_sender'),
                (bool) config('graph-mail.save_to_sent_items', true)
            );
        });
    }
}
