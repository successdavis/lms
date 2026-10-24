<?php

namespace App\Events;

use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payroll hook: dispatched when a promotion takes effect on the staff
 * record. Listen for it to push the new grade level to an external payroll
 * (e.g. IPPIS) integration.
 */
class StaffPromoted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Promotion $promotion) {}
}
