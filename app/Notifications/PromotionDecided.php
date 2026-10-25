<?php

namespace App\Notifications;

use App\Models\Promotion;

class PromotionDecided extends PortalNotification
{
    public static function for(Promotion $promotion): self
    {
        $to = $promotion->to_designation ?? "grade level {$promotion->to_grade_level}";

        return $promotion->status === 'approved'
            ? new self(
                title: "Promotion approved: {$to}",
                body: "Congratulations! Your promotion to {$to} (grade level {$promotion->to_grade_level}) takes effect from "
                    .$promotion->effective_on->toFormattedDateString().'.',
            )
            : new self(
                title: 'Promotion not approved',
                body: "Your recommended promotion to {$to} was not approved.".($promotion->note ? " Note: {$promotion->note}" : ''),
            );
    }
}
