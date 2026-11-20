<?php

namespace App\Pages\Projects;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

final class ProjectFormPage
{
    /** @return array<string, mixed> */
    public static function create(string $search): array
    {
        return [
            ...ProjectListPage::props($search),
            'creating' => true,
            'clients' => self::clients(),
        ];
    }

    /**
     * A Project's own page offers the same Clients when it is edited there.
     *
     * @return Collection<int, Client>
     */
    public static function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name']);
    }
}
