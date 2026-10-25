<?php

namespace App\Notifications;

use App\Models\Applicant;

class AdmissionOffered extends PortalNotification
{
    public static function for(Applicant $applicant): self
    {
        $programme = ($applicant->admittedProgramme ?? $applicant->programme)->name;

        return new self(
            title: 'Offer of admission',
            body: "Congratulations! You have been offered provisional admission into {$programme}. Log in to accept the offer and print your admission letter.",
            url: '/apply',
            sms: "Congratulations! You have been offered admission into {$programme}. Log in to accept.",
        );
    }
}
