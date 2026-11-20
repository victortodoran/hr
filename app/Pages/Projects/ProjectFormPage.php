<?php

namespace App\Pages\Projects;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Projects page with the sheet for a new Project open over it (rule
 * L14), as the props it renders: the Clients a Project can be for. A
 * Project's own page offers the same Clients when it is edited there.
 */
final class ProjectFormPage
{
    /**
     * The Projects page's props (ProjectListPage) for the search the list
     * had, that the sheet is open, and the Clients it offers.
     *
     * @return array<string, mixed>
     */
    public static function create(string $search): array
    {
        return [
            ...ProjectListPage::props($search),
            'creating' => true,
            'clients' => self::clients(),
        ];
    }

    /**
     * The Clients a Project can be for, by name.
     *
     * @return Collection<int, Client>
     */
    public static function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name']);
    }
}
