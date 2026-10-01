<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Probation = 'probation';
    case Suspended = 'suspended';
    case Deferred = 'deferred';        // approved deferment of session
    case Spillover = 'spillover';      // past normal duration, outstanding courses
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';
}
