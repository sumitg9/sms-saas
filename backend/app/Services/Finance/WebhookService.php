<?php

namespace App\Services\Finance;

use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Process incoming Razorpay webhook with signature verification and idempotency check.
     */
    public function handleRazorpayWebhook(string $rawPayload, string $signature): array
    {
        $webhookSecret = config('services.razorpay.webhook_secret');

        // Signature verification
        $expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::error('Razorpay Webhook: Invalid signature', [
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
            throw new InvalidArgumentException('Invalid Razorpay webhook signature.');
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload in webhook.');
        }

        $eventId = $payload['event_id'] ?? $payload['id'] ?? null;
        $eventType = $payload['event'] ?? 'unknown';

        if (!$eventId) {
            $eventId = 'evt_' . md5($rawPayload);
        }

        // Idempotency check: prevent duplicate replay of the same event
        $existingEvent = PaymentWebhookEvent::where('event_id', $eventId)->first();
        if ($existingEvent) {
            return [
                'status' => 'duplicate',
                'message' => 'Event already processed',
                'event_id' => $eventId,
            ];
        }

        // Extract tenant ID if present in notes
        $tenantId = null;
        if (isset($payload['payload']['payment']['entity']['notes']['tenant_id'])) {
            $tenantId = $payload['payload']['payment']['entity']['notes']['tenant_id'];
        }

        // Store webhook event
        $webhookEvent = PaymentWebhookEvent::create([
            'tenant_id' => $tenantId,
            'gateway' => 'razorpay',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'payload' => $payload,
            'is_processed' => false,
        ]);

        // Process event types
        DB::transaction(function () use ($webhookEvent, $payload, $eventType) {
            if ($eventType === 'payment.captured') {
                $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
                $orderId = $paymentEntity['order_id'] ?? null;
                $paymentId = $paymentEntity['id'] ?? null;

                if ($orderId) {
                    $transaction = PaymentTransaction::where('gateway_order_id', $orderId)->first();
                    if ($transaction && $transaction->status !== 'captured') {
                        $transaction->update([
                            'gateway_payment_id' => $paymentId,
                            'status' => 'captured',
                        ]);
                    }
                }
            } elseif ($eventType === 'payment.failed') {
                $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
                $orderId = $paymentEntity['order_id'] ?? null;

                if ($orderId) {
                    $transaction = PaymentTransaction::where('gateway_order_id', $orderId)->first();
                    if ($transaction) {
                        $transaction->update(['status' => 'failed']);
                    }
                }
            }

            $webhookEvent->update([
                'is_processed' => true,
                'processed_at' => Carbon::now(),
            ]);
        });

        return [
            'status' => 'success',
            'message' => 'Webhook handled successfully',
            'event_id' => $eventId,
            'event_type' => $eventType,
        ];
    }
}

