<?php

namespace App\Pages\Projects;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Clients page as the props it renders: the Clients the company does
 * work for.
 */
final class ClientListPage
{
    /**
     * Every Client by name, with how many Projects each has.
     *
     * @return array{clients: Collection<int, Client>}
     */
    public static function props(): array
    {
        return [
            'clients' => Client::query()->select(['id', 'name'])->withCount('projects')->orderBy('name')->get(),
        ];
    }
}
