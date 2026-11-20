<?php

namespace App\Enums;

/**
 * Where a Pay change in an Assignment's Pay history stands on a day:
 * Upcoming until it takes effect, In effect while it is the latest to have
 * taken effect, and Past once a later one has. The values are the Pay
 * change's status on the page.
 */
enum PayChangeState: string
{
    case Upcoming = 'upcoming';
    case InEffect = 'in-effect';
    case Past = 'past';
}
