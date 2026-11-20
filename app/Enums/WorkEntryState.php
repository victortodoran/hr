<?php

namespace App\Enums;

/** Approval is per Work Entry (ADR 0005). */
enum WorkEntryState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
