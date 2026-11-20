<?php

namespace App\Enums;

/** The values are shown on the page as the Pay change's status. */
enum PayChangeState: string
{
    case Upcoming = 'upcoming';
    case InEffect = 'in-effect';
    case Past = 'past';
}
