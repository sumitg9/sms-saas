<?php

namespace App\Contracts\Communication;

interface EmailProviderInterface
{
    /**
     * Send an email notification.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body Email content (HTML or plain text)
     * @param array $options Additional options (attachments, reply_to, cc, bcc)
     * @return array [success => bool, message_id => string|null, error => string|null, raw_response => mixed]
     */
    public function send(string $to, string $subject, string $body, array $options = []): array;
}

