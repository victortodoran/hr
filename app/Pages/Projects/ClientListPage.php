<?php

namespace App\Pages\Projects;

use App\Models\Client;

final class ClientListPage
{
    /** @return array<string, mixed> */
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
