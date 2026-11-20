<?php

namespace App\Models\Builders;

use App\Models\Client;
use App\Models\Concerns\SearchesByName;

/**
 * Every reusable query on Clients.
 *
 * @extends ModelBuilder<Client>
 */
class ClientBuilder extends ModelBuilder
{
    use SearchesByName;
}
