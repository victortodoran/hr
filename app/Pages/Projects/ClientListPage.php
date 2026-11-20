<?php

namespace App\Pages\Projects;

use App\Models\Client;

/**
 * The Clients page as the props it renders: the Clients the company does
 * work for, a page at a time.
 */
final class ClientListPage
{
    /**
     * The Clients by name, with how many Projects each has, optionally
     * searched by part of a name.
     *
     * @return array<string, mixed>
     */
    public static function props(string $search): array
    {
        return [
            'clients' => Client::query()
                ->select(['id', 'name'])
                ->withCount('projects')
                ->whereNameContains($search)
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ];
    }
}
