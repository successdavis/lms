<?php

namespace App\Enums;

enum EntryMode: string
{
    case Utme = 'utme';                 // JAMB UTME (100L / ND1 entry)
    case DirectEntry = 'direct_entry';  // DE into 200L
    case Transfer = 'transfer';
    case Hnd = 'hnd';                   // ND holder entering HND1 (polytechnic)
}
