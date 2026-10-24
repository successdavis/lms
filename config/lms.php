<?php

/*
| Institution-level policy knobs for Phase 4 modules. Defaults follow the
| practices documented in docs/research/.
*/

return [

    // Transcript fees in naira by destination and delivery mode. E-copies go
    // straight to the receiving institution's email; hard copies are posted.
    'transcript_fees' => [
        'local' => ['e_copy' => 5000, 'hard_copy' => 15000],
        'international' => ['e_copy' => 20000, 'hard_copy' => 50000],
    ],

    'hostel' => [
        // Unpaid bed-space reservations lapse after this many hours.
        'reservation_hours' => (int) env('HOSTEL_RESERVATION_HOURS', 48),
    ],

    'siwes' => [
        // NBTE ND SIWES runs ~4 months; many university programmes use 24 weeks.
        'min_weeks' => (int) env('SIWES_MIN_WEEKS', 16),
        'pass_mark' => 40,
    ],

    'leave' => [
        // Annual leave entitlement in working days per calendar year.
        'annual_days' => (int) env('ANNUAL_LEAVE_DAYS', 30),
    ],

    'nysc' => [
        // Graduates aged 30+ at graduation receive an exemption certificate
        // instead of being mobilized.
        'max_age' => 30,
    ],

];
