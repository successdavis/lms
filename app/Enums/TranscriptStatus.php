<?php

namespace App\Enums;

enum TranscriptStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';             // in the records office queue
    case Processing = 'processing'; // compiled, awaiting registrar's signature
    case Dispatched = 'dispatched'; // emailed / posted to the recipient
    case Rejected = 'rejected';
}
