<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A portal notice delivered in-app, by email, and (when an SMS text is given
 * and SMS is enabled) by text message. Subclasses only name the event and
 * build the content, so tests can assert exactly which notice went out.
 */
abstract class PortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $sms = null,
    ) {
        // Queued notices wait for the surrounding DB transaction to commit.
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($this->sms !== null && config('services.sms.enabled')) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body);

        return $this->url ? $mail->action('Open the portal', url($this->url)) : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'kind' => class_basename(static::class),
        ];
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->sms === null ? null : mb_substr(config('app.name').': '.$this->sms, 0, 300);
    }
}
