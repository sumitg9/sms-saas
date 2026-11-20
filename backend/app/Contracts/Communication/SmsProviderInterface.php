<?php

namespace App\Contracts\Communication;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $to Recipient phone number (E.164 formatted)
     * @param string $message Text content
     * @param array $options Additional options (sender_id, dlt_template_id, etc.)
     * @return array [success => bool, message_id => string|null, error => string|null, raw_response => mixed]
     */
    public function send(string $to, string $message, array $options = []): array;
}

