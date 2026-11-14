<?php

namespace App\Enums;

/**
 * Where a Work Entry stands: Pending until someone approves or declines it,
 * Approved once its hours are accepted, Locked for good once it is closed,
 * for example because it was invoiced or paid (ADR 0005), and Declined when
 * its Manager refused it with a reason, so its hours count nowhere.
 */
enum WorkEntryState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Locked = 'locked';
    case Declined = 'declined';
}
