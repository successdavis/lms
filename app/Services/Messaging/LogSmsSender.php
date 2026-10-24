<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes SMS to the application log instead of sending.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info("SMS to {$to}: {$message}");
    }
}
