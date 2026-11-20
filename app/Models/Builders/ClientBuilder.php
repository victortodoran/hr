<?php

namespace App\Models\Builders;

use App\Models\Client;
use App\Models\Concerns\SearchesByName;

/** @extends ModelBuilder<Client> */
class ClientBuilder extends ModelBuilder
{
    use SearchesByName;
}
