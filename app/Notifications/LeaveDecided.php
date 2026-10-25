<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveDecided extends PortalNotification
{
    public static function for(LeaveRequest $leave): self
    {
        $period = $leave->starts_on->toFormattedDateString().' – '.$leave->ends_on->toFormattedDateString();

        return new self(
            title: "Leave request {$leave->status}",
            body: "Your {$leave->type->value} leave for {$period} ({$leave->days} working days) was {$leave->status}."
                .($leave->note ? " Note: {$leave->note}" : ''),
            url: '/staff/leave',
        );
    }
}
