<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Termii (termii.com), widely used for transactional SMS in Nigeria. The
 * "dnd" channel delivers to numbers on the Do-Not-Disturb register, which is
 * required for reliable delivery of school notices.
 */
class TermiiSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        $apiKey = config('services.sms.termii.api_key') ?: throw new RuntimeException('TERMII_API_KEY is not configured.');

        Http::post(rtrim(config('services.sms.termii.base_url'), '/').'/api/sms/send', [
            'api_key' => $apiKey,
            'to' => $to,
            'from' => config('services.sms.termii.sender_id'),
            'sms' => $message,
            'type' => 'plain',
            'channel' => config('services.sms.termii.channel'),
        ])->throw();
    }
}
