<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Semester;

class ResultsPublished extends PortalNotification
{
    public static function for(Course $course, Semester $semester): self
    {
        return new self(
            title: "Result released: {$course->code}",
            body: "Your {$course->code} ({$course->title}) result for the {$semester->name} has been approved and released.",
            url: '/student/results',
            sms: "{$course->code} result for {$semester->name} is now available on the portal.",
        );
    }
}
