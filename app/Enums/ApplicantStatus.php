<?php

namespace App\Enums;

enum ApplicantStatus: string
{
    case Draft = 'draft';              // application started, not yet submitted
    case Submitted = 'submitted';      // awaiting screening
    case Screened = 'screened';        // post-UTME scored, aggregate computed
    case Admitted = 'admitted';        // on a published admission list
    case Accepted = 'accepted';        // offer accepted by the candidate
    case Matriculated = 'matriculated'; // converted to a student record
    case Rejected = 'rejected';        // not offered admission this cycle
}
