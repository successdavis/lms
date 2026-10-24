<?php

namespace App\Notifications;

use App\Enums\SiwesStatus;
use App\Models\SiwesPlacement;

class SiwesUpdated extends PortalNotification
{
    public static function for(SiwesPlacement $placement): self
    {
        return match ($placement->status) {
            SiwesStatus::Approved => new self(
                title: 'SIWES placement approved',
                body: "Your placement at {$placement->employer_name} is approved. Keep your ITF logbook signed weekly.",
                url: '/student/siwes',
            ),
            SiwesStatus::Graded => new self(
                title: 'SIWES graded',
                body: "Your SIWES was graded {$placement->total_score}/100 (".($placement->is_passed ? 'pass' : 'fail').').',
                url: '/student/siwes',
            ),
            default => new self(
                title: 'SIWES placement not approved',
                body: "Your placement at {$placement->employer_name} was not approved. Update the details and resubmit.",
                url: '/student/siwes',
            ),
        };
    }
}
