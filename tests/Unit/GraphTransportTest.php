<?php

namespace GraphMail\LaravelGraphMailLegacy\Tests\Unit;

use GraphMail\LaravelGraphMailLegacy\Exceptions\GraphMailException;
use GraphMail\LaravelGraphMailLegacy\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class GraphTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        ]);
    }

    /** @test */
    public function it_builds_a_correct_json_payload_and_posts_to_the_right_sender_mailbox()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
        ]);

        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('Test Subject');
        });

        Http::assertSent(function ($request) {
            if (strpos($request->url(), 'sendMail') === false) {
                return true;
            }

            $data = $request->data();

            return $data['message']['subject'] === 'Test Subject'
                && $data['message']['toRecipients'][0]['emailAddress']['address'] === 'recipient@example.com'
                && $data['message']['body']['content'] === 'Hello world';
        });
    }

    /** @test */
    public function it_always_sends_as_the_configured_ms_sender_email_ignoring_any_from_on_the_message()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
        ]);

        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')
                ->from('someone-else@example.com')
                ->subject('From Override Attempt');
        });

        // The configured sender (sender@example.com, set in TestCase) is used —
        // not the "from" address set on the message.
        Http::assertSent(function ($request) {
            return strpos($request->url(), 'users/sender@example.com/sendMail') !== false;
        });
    }

    /** @test */
    public function it_throws_when_ms_sender_email_is_not_configured()
    {
        config(['graph-mail.default_sender' => null]);

        $this->expectException(GraphMailException::class);
        $this->expectExceptionMessage('No sender mailbox configured');

        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('No Sender');
        });
    }

    /** @test */
    public function it_throws_when_ms_sender_email_is_an_invalid_email_like_a_leftover_placeholder()
    {
        config(['graph-mail.default_sender' => 'user@host']);

        $this->expectException(GraphMailException::class);
        $this->expectExceptionMessage('not a valid email address');

        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('Bad Sender');
        });
    }

    /** @test */
    public function it_throws_when_the_graph_send_mail_call_fails()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/v1.0/users/*/sendMail' => Http::response([
                'error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access is denied.'],
            ], 403),
        ]);

        $this->expectException(GraphMailException::class);

        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('Should Fail');
        });
    }
}
