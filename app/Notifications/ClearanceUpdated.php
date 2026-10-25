<?php

namespace App\Notifications;

use App\Models\ClearanceItem;

class ClearanceUpdated extends PortalNotification
{
    public static function rejected(ClearanceItem $item): self
    {
        return new self(
            title: "Clearance: {$item->unit->label()} needs attention",
            body: "{$item->unit->label()} did not clear you: {$item->note}. Resolve it, then resubmit from the clearance page.",
            url: '/student/clearance',
            sms: "Clearance: {$item->unit->label()} needs attention. Check the portal.",
        );
    }

    public static function completed(): self
    {
        return new self(
            title: 'Final clearance complete',
            body: 'Every unit has cleared you. You will be considered for the graduation list.',
            url: '/student/clearance',
            sms: 'Your final-year clearance is complete.',
        );
    }
}
