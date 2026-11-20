<?php

namespace App\Contracts\Communication;

interface WhatsAppProviderInterface
{
    /**
     * Send a WhatsApp message or template message.
     *
     * @param string $to Recipient phone number (E.164 formatted)
     * @param string $message Plain text message (if session open)
     * @param string|null $templateName Pre-approved Meta/WhatsApp template name
     * @param array $templateParams Template parameters / variables
     * @param array $options Additional options (media_url, buttons, etc.)
     * @return array [success => bool, message_id => string|null, error => string|null, raw_response => mixed]
     */
    public function send(
        string $to,
        string $message,
        ?string $templateName = null,
        array $templateParams = [],
        array $options = []
    ): array;
}

