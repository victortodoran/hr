<?php

namespace App\Enums;

/**
 * Where a Work Entry stands: Pending until someone approves it, Approved
 * once its hours are accepted, and Locked for good once it is closed, for
 * example because it was invoiced or paid (ADR 0005).
 */
enum WorkEntryState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Locked = 'locked';
}
