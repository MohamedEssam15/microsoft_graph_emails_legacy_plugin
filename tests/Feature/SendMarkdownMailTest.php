<?php

namespace GraphMail\LaravelGraphMailLegacy\Tests\Feature;

use GraphMail\LaravelGraphMailLegacy\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class SendMarkdownMailTest extends TestCase
{
    /**
     * Regression test for a bug where any message with BOTH an HTML body
     * and a text/plain alternative part (as produced by @component('mail::message')
     * Markdown mailables, or any Mailable using ->view() + ->text()) would
     * have its HTML body silently dropped and only the plain-text fallback
     * sent to Graph.
     *
     * Root cause: Swift_Mime_SimpleMessage::getContentType() reports the
     * *MIME structure* header, which SwiftMailer rewrites to
     * "multipart/alternative" as soon as a second part is attached via
     * addPart(). It no longer reports "text/html" once that happens, so
     * naively branching on getContentType() to detect the main body type
     * fails for any multi-part message.
     *
     * @test
     */
    public function it_sends_the_html_body_not_the_plain_text_fallback_when_message_has_both_parts()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
        ]);

        Mail::send(
            [
                'html' => new HtmlString('<p>Reset your Password</p><a href="https://example.com">Reset Password</a>'),
                'text' => 'raw-text-fallback', // resolved via addPart(), not setBody()
            ],
            [],
            function ($message) {
                $message->to('recipient@example.com')->subject('Reset your Password');
            }
        );

        Http::assertSent(function ($request) {
            if (strpos($request->url(), 'sendMail') === false) {
                return true;
            }

            $data = $request->data();

            return $data['message']['body']['contentType'] === 'HTML'
                && strpos($data['message']['body']['content'], 'Reset Password') !== false
                && strpos($data['message']['body']['content'], 'raw-text-fallback') === false;
        });
    }
}
