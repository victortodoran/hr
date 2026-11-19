<?php

namespace App\Pages\Projects;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

/**
 * The form for adding a Project, as the props it renders: the Clients a
 * Project can be for. A Project's own page offers the same Clients when
 * it is changed there.
 */
final class ProjectFormPage
{
    /**
     * The form for adding a Project.
     *
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return [
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
