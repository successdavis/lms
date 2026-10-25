<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\SmsSender;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification);
        $message = method_exists($notification, 'toSms') ? $notification->toSms($notifiable) : null;

        if (! $phone || ! $message) {
            return;
        }

        $this->sender->send(self::normalise($phone), $message);
    }

    /**
     * Nigerian numbers to international format: 0803... / +234803... -> 234803...
     */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '0') ? '234'.substr($digits, 1) : $digits;
    }
}
