<?php

namespace App\Notifications;

use App\Models\Student;

class Matriculated extends PortalNotification
{
    public static function for(Student $student): self
    {
        return new self(
            title: 'Welcome — your matriculation number',
            body: "You have been matriculated into {$student->programme->name}. Your matriculation number is {$student->matric_no}. Generate and pay your school fees to register courses.",
            url: '/student/dashboard',
            sms: "Welcome! Your matric number is {$student->matric_no}.",
        );
    }
}
