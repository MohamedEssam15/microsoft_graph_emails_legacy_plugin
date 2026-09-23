<?php

namespace GraphMail\LaravelGraphMailLegacy\Transport;

use GraphMail\LaravelGraphMailLegacy\Exceptions\GraphMailException;
use GraphMail\LaravelGraphMailLegacy\Services\MicrosoftGraphTokenService;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Facades\Http;
use Swift_Mime_Attachment;
use Swift_Mime_SimpleMessage;

class GraphTransport extends Transport
{
    /**
     * @var MicrosoftGraphTokenService
     */
    private $tokenService;

    /**
     * @var string|null
     */
    private $defaultSender;

    /**
     * @var bool
     */
    private $saveToSentItems;

    /**
     * @param MicrosoftGraphTokenService $tokenService
     * @param string|null $defaultSender
     * @param bool $saveToSentItems
     */
    public function __construct(MicrosoftGraphTokenService $tokenService, $defaultSender, $saveToSentItems = true)
    {
        $this->tokenService = $tokenService;
        $this->defaultSender = $defaultSender;
        $this->saveToSentItems = $saveToSentItems;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $sender = $this->resolveSender();
        $payload = $this->buildPayload($message);
        $token = $this->tokenService->getAccessToken();

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", $payload);

        if ($response->failed()) {
            throw GraphMailException::sendFailed($response->body(), $response->status());
        }

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * The sender is always the configured MS_SENDER_EMAIL — deliberately
     * ignoring any "from" address set on the message or in config/mail.php.
     *
     * This is intentional: Laravel's global mail.from config (or a stray
     * ->from() call, or an unedited scaffold placeholder like "user@host")
     * has no relationship to which mailbox your Azure AD app is actually
     * permitted to send as via Mail.Send. Using it here would let a random
     * config value silently override the one mailbox you've actually
     * granted Graph API access to, producing a confusing 404 from Graph
     * instead of a clear local error. If you need to send from a different
     * permitted mailbox, change MS_SENDER_EMAIL, not the Mailable.
     *
     * @return string
     */
    private function resolveSender()
    {
        $sender = $this->defaultSender;

        if (empty($sender)) {
            throw GraphMailException::missingSender();
        }

        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw GraphMailException::invalidSender($sender);
        }

        return $sender;
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function buildPayload(Swift_Mime_SimpleMessage $message)
    {
        list($htmlBody, $textBody) = $this->resolveBodies($message);

        $graphMessage = [
            'subject' => $message->getSubject() ?: '',
            'body' => [
                'contentType' => $htmlBody !== null ? 'HTML' : 'Text',
                'content' => $htmlBody !== null ? $htmlBody : ($textBody !== null ? $textBody : ''),
            ],
            'toRecipients' => $this->mapAddresses($message->getTo()),
        ];

        if ($cc = $this->mapAddresses($message->getCc())) {
            $graphMessage['ccRecipients'] = $cc;
        }

        if ($bcc = $this->mapAddresses($message->getBcc())) {
            $graphMessage['bccRecipients'] = $bcc;
        }

        if ($replyTo = $this->mapAddresses($message->getReplyTo())) {
            $graphMessage['replyTo'] = $replyTo;
        }

        if ($attachments = $this->buildAttachments($message)) {
            $graphMessage['attachments'] = $attachments;
        }

        $payload = ['message' => $graphMessage];

        // Per the Graph API docs, only specify saveToSentItems when false;
        // true is the default. We still allow forcing false via config.
        if (!$this->saveToSentItems) {
            $payload['saveToSentItems'] = false;
        }

        return $payload;
    }

    /**
     * Resolve the HTML and plain-text bodies of the message.
     *
     * The "main" body of a Swift_Mime_SimpleMessage is whichever part was
     * set last (usually via the Mailable's ->html()/->text() or view
     * rendering). When the message is multipart/alternative, the other
     * representation lives in a child Swift_MimePart. This inspects both
     * the main body and any children to recover both representations,
     * mirroring Symfony Mailer's Email::getHtmlBody()/getTextBody().
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array{0: string|null, 1: string|null} [$htmlBody, $textBody]
     */
    private function resolveBodies(Swift_Mime_SimpleMessage $message)
    {
        $htmlBody = null;
        $textBody = null;

        $contentType = $message->getContentType();
        $body = $message->getBody();

        if ($contentType === 'text/html') {
            $htmlBody = $body;
        } elseif ($contentType === 'text/plain') {
            $textBody = $body;
        }

        foreach ($message->getChildren() as $child) {
            if (!method_exists($child, 'getContentType') || !method_exists($child, 'getBody')) {
                continue;
            }

            $childContentType = $child->getContentType();

            if ($childContentType === 'text/html' && $htmlBody === null) {
                $htmlBody = $child->getBody();
            } elseif ($childContentType === 'text/plain' && $textBody === null) {
                $textBody = $child->getBody();
            }
        }

        return [$htmlBody, $textBody];
    }

    /**
     * @param array|null $addresses Associative array of email => name.
     * @return array
     */
    private function mapAddresses($addresses)
    {
        $mapped = [];

        foreach ((array) $addresses as $email => $name) {
            $mapped[] = ['emailAddress' => ['address' => $email]];
        }

        return $mapped;
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function buildAttachments(Swift_Mime_SimpleMessage $message)
    {
        $attachments = [];

        foreach ($message->getChildren() as $child) {
            if (!($child instanceof Swift_Mime_Attachment)) {
                continue;
            }

            // Exclude inline embeds (e.g. images referenced via cid: in HTML
            // bodies) — only real attachments should be sent to Graph.
            if ($child->getDisposition() === 'inline') {
                continue;
            }

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $child->getFilename() ?: 'attachment',
                'contentType' => $child->getContentType(),
                'contentBytes' => base64_encode($child->getBody()),
            ];
        }

        return $attachments;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'graph';
    }
}
