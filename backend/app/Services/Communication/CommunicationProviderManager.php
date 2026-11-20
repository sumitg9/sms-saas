<?php

namespace App\Services\Communication;

use App\Contracts\Communication\EmailProviderInterface;
use App\Contracts\Communication\PushProviderInterface;
use App\Contracts\Communication\SmsProviderInterface;
use App\Contracts\Communication\WhatsAppProviderInterface;
use App\Services\Communication\Providers\LaravelEmailProvider;
use App\Services\Communication\Providers\LogPushProvider;
use App\Services\Communication\Providers\LogSmsProvider;
use App\Services\Communication\Providers\LogWhatsAppProvider;
use App\Services\Communication\Providers\MetaWhatsAppProvider;
use App\Services\Communication\Providers\TwilioSmsProvider;

class CommunicationProviderManager
{
    protected ?SmsProviderInterface $smsProvider = null;
    protected ?WhatsAppProviderInterface $whatsappProvider = null;
    protected ?EmailProviderInterface $emailProvider = null;
    protected ?PushProviderInterface $pushProvider = null;

    public function sms(): SmsProviderInterface
    {
        if ($this->smsProvider) {
            return $this->smsProvider;
        }

        $driver = config('communication.sms_driver', 'log');

        return match ($driver) {
            'twilio' => app(TwilioSmsProvider::class),
            default => app(LogSmsProvider::class),
        };
    }

    public function setSmsProvider(SmsProviderInterface $provider): self
    {
        $this->smsProvider = $provider;
        return $this;
    }

    public function whatsapp(): WhatsAppProviderInterface
    {
        if ($this->whatsappProvider) {
            return $this->whatsappProvider;
        }

        $driver = config('communication.whatsapp_driver', 'log');

        return match ($driver) {
            'meta' => app(MetaWhatsAppProvider::class),
            default => app(LogWhatsAppProvider::class),
        };
    }

    public function setWhatsAppProvider(WhatsAppProviderInterface $provider): self
    {
        $this->whatsappProvider = $provider;
        return $this;
    }

    public function email(): EmailProviderInterface
    {
        if ($this->emailProvider) {
            return $this->emailProvider;
        }

        return app(LaravelEmailProvider::class);
    }

    public function setEmailProvider(EmailProviderInterface $provider): self
    {
        $this->emailProvider = $provider;
        return $this;
    }

    public function push(): PushProviderInterface
    {
        if ($this->pushProvider) {
            return $this->pushProvider;
        }

        return app(LogPushProvider::class);
    }

    public function setPushProvider(PushProviderInterface $provider): self
    {
        $this->pushProvider = $provider;
        return $this;
    }
}

