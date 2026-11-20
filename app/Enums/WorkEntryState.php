<?php

namespace App\Enums;

enum WorkEntryState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
