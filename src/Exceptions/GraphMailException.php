<?php

namespace GraphMail\LaravelGraphMailLegacy\Exceptions;

use Exception;

class GraphMailException extends Exception
{
    /**
     * @var int
     */
    private $httpStatus;

    public function __construct($message, $httpStatus = 0)
    {
        parent::__construct($message);

        $this->httpStatus = $httpStatus;
    }

    /**
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * @param string $body
     * @return self
     */
    public static function tokenRequestFailed($body)
    {
        return new self("Failed to acquire Microsoft Graph access token: {$body}");
    }

    /**
     * @param string $body
     * @param int $status
     * @return self
     */
    public static function sendFailed($body, $status)
    {
        return new self("Microsoft Graph sendMail request failed ({$status}): {$body}", $status);
    }

    /**
     * @return self
     */
    public static function missingSender()
    {
        return new self(
            'No sender mailbox configured. Set MS_SENDER_EMAIL in your .env '
            . '(graph-mail.default_sender). The transport always sends as this '
            . 'address — it does not read the "from" address on Mailables or '
            . 'config/mail.php.'
        );
    }

    /**
     * @param string $sender
     * @return self
     */
    public static function invalidSender($sender)
    {
        return new self(
            "MS_SENDER_EMAIL is set to \"{$sender}\", which is not a valid email address. "
            . 'Check your .env for a leftover placeholder (e.g. "user@host") or a typo, '
            . 'then run php artisan config:clear.'
        );
    }
}
