<?php

namespace App\Contracts\Communication;

interface PushProviderInterface
{
    /**
     * Send a mobile push notification (e.g. Firebase Cloud Messaging / APNs).
     *
     * @param string $toTokenOrTopic Device token or topic string
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Custom payload data
     * @return array [success => bool, message_id => string|null, error => string|null, raw_response => mixed]
     */
    public function send(string $toTokenOrTopic, string $title, string $body, array $data = []): array;
}

