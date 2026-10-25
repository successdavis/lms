<?php

namespace App\Notifications;

use App\Models\HostelAllocation;

class HostelReserved extends PortalNotification
{
    public static function for(HostelAllocation $allocation): self
    {
        $where = "{$allocation->room->hostel->name}, Room {$allocation->room->number}";
        $deadline = $allocation->expires_at->toDayDateTimeString();

        return new self(
            title: "Bed space reserved: {$where}",
            body: "A bed space in {$where} is held for you until {$deadline}. Pay the hostel invoice before then or the space will be released.",
            url: '/student/hostel',
            sms: "Bed space reserved in {$where}. Pay by {$deadline} to keep it.",
        );
    }
}
