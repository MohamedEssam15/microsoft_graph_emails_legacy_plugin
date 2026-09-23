<?php

namespace GraphMail\LaravelGraphMailLegacy\Console;

use GraphMail\LaravelGraphMailLegacy\Services\MicrosoftGraphTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestGraphMailCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'graph-mail:test {to? : Recipient email address (defaults to the configured sender)}';

    /**
     * @var string
     */
    protected $description = 'Test Microsoft Graph mail credentials and the Laravel Mail transport end-to-end';

    /**
     * @param MicrosoftGraphTokenService $tokenService
     * @return int
     */
    public function handle(MicrosoftGraphTokenService $tokenService)
    {
        $to = $this->argument('to') ?: config('graph-mail.default_sender');

        if (empty($to)) {
            $this->error('No recipient given and no default sender configured. Pass one: php artisan graph-mail:test someone@example.com');

            return 1;
        }

        $this->info('Step 1/3: Acquiring access token...');
        $tokenService->forgetToken();

        try {
            $token = $tokenService->getAccessToken();
            $this->info('  OK — token acquired (' . substr($token, 0, 16) . '...)');
        } catch (Throwable $e) {
            $this->error('  FAILED — ' . $e->getMessage());
            $this->newLine();
            $this->warn('Common causes: wrong client secret (Secret ID used instead of Value), expired secret, wrong tenant/client ID.');

            return 1;
        }

        $this->info('Step 2/3: Direct Graph API sendMail call...');
        $sender = config('graph-mail.default_sender');

        try {
            $response = Http::withToken($token)->post(
                "https://graph.microsoft.com/v1.0/users/{$sender}/sendMail",
                [
                    'message' => [
                        'subject' => 'graph-mail:test — ' . now()->toDateTimeString(),
                        'body' => [
                            'contentType' => 'Text',
                            'content' => 'This is a direct Graph API test sent by the graph-mail:test artisan command.',
                        ],
                        'toRecipients' => [
                            ['emailAddress' => ['address' => $to]],
                        ],
                    ],
                ]
            );

            if ($response->successful()) {
                $this->info('  OK — Graph accepted the message (HTTP ' . $response->status() . ')');
            } else {
                $this->error('  FAILED — HTTP ' . $response->status() . ': ' . $response->body());
                $this->printTroubleshooting($response->status());

                return 1;
            }
        } catch (Throwable $e) {
            $this->error('  FAILED — ' . $e->getMessage());

            return 1;
        }

        $this->info('Step 3/3: Laravel Mail via the graph transport...');

        try {
            Mail::raw(
                'This is a test sent via Laravel Mail using the graph-mail package transport.',
                function ($message) use ($to) {
                    $message->to($to)->subject('graph-mail:test — Laravel Mail transport');
                }
            );

            $this->info('  OK — sent through Mail::raw()');
        } catch (Throwable $e) {
            $this->error('  FAILED — ' . $e->getMessage());

            return 1;
        }

        $this->newLine();
        $this->info('All checks passed. Your Microsoft Graph mail setup is working.');

        return 0;
    }

    /**
     * @param int $status
     * @return void
     */
    private function printTroubleshooting($status)
    {
        $this->newLine();

        if ($status === 403) {
            $this->warn('403 ErrorAccessDenied usually means one of:');
            $this->line('  1. Admin consent was not actually granted for the Mail.Send application permission.');
            $this->line('     Verify via Graph API, not just the portal checkmark:');
            $this->line('     GET /servicePrincipals?$filter=appId eq \'{your-client-id}\'');
            $this->line('     GET /servicePrincipals/{id}/appRoleAssignments');
            $this->line('     If the result is empty, redo admin consent as a Global Administrator.');
            $this->line('  2. An Exchange Online Application Access Policy is scoping your app to mailboxes');
            $this->line('     that do not include your sender. Ask an Exchange admin to run:');
            $this->line('     Get-ApplicationAccessPolicy');
            $this->line('  3. The sender mailbox is not a licensed user/shared mailbox.');
        } elseif ($status === 401) {
            $this->warn('401 usually means the token is invalid/expired, or the app lacks any Graph permission at all.');
        } elseif ($status === 405) {
            $this->warn('405 usually means the sender URL is malformed — check MS_GRAPH_SENDER for empty values, quotes, or trailing whitespace.');
        }
    }
}
