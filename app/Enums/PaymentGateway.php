<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Paystack = 'paystack';
    case Remita = 'remita';        // federal institutions (TSA), uses RRR references
    case Flutterwave = 'flutterwave';
    case Interswitch = 'interswitch';
    case Bank = 'bank';            // bank teller / transfer, manually confirmed
}
