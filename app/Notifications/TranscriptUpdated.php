<?php

namespace App\Notifications;

use App\Enums\TranscriptStatus;
use App\Models\TranscriptRequest;

class TranscriptUpdated extends PortalNotification
{
    public static function for(TranscriptRequest $transcript): self
    {
        return $transcript->status === TranscriptStatus::Dispatched
            ? new self(
                title: "Transcript {$transcript->number} dispatched",
                body: "Your transcript has been sent to {$transcript->recipient_name}. Dispatch reference: {$transcript->dispatch_reference}.",
                url: '/student/transcripts',
                sms: "Transcript {$transcript->number} sent to {$transcript->recipient_name}. Ref {$transcript->dispatch_reference}.",
            )
            : new self(
                title: "Transcript {$transcript->number} not processed",
                body: "The records office could not process your transcript request: {$transcript->remarks}",
                url: '/student/transcripts',
            );
    }
}
