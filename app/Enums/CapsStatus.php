<?php

namespace App\Enums;

/**
 * Where the candidate stands on JAMB's Central Admissions Processing System.
 * Tracked manually by the admissions office — CAPS has no public API.
 */
enum CapsStatus: string
{
    case NotUploaded = 'not_uploaded';   // institution yet to recommend on CAPS
    case Recommended = 'recommended';    // uploaded/recommended by the institution
    case Approved = 'approved';          // JAMB approved the admission
    case Accepted = 'accepted';          // candidate accepted on CAPS
    case Rejected = 'rejected';          // candidate rejected / JAMB declined
}
