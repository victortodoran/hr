<?php

namespace App\Pages\Search;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;

final class CommandMenuResults
{
    public const int LIMIT = 5;

    /** @return array{people: list<array{id: int, name: string, job_title: string|null, canViewEmployee: bool}>, projects: list<array{id: int, name: string, client: string|null}>, clients: list<array{id: int, name: string}>} */
    public static function props(Employee $viewer, string $search): array
    {
        if (trim($search) === '') {
            return ['people' => [], 'projects' => [], 'clients' => []];
        }

        $search = trim($search);

        return [
            'people' => self::people($viewer, $search),
            'projects' => $viewer->can('viewAny', Project::class) ? self::projects($search) : [],
            'clients' => $viewer->can(Permission::ManageClients->value) ? self::clients($search) : [],
        ];
    }

    /**
     * Whether each person opens on their Employee page comes from the same withPageVisibleTo() that findableBy() lets them through by, so the link and the search can't disagree.
     *
     * @return list<array{id: int, name: string, job_title: string|null, canViewEmployee: bool}>
     */
    private static function people(Employee $viewer, string $search): array
    {
        $people = Employee::query()
            ->findableBy($viewer)
            ->whereNameContains($search)
            ->with('internalAssignment:id,employee_id,project_id,job_title')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $withPage = Employee::query()
            ->withPageVisibleTo($viewer)
            ->whereKey($people->modelKeys())
            ->pluck('employees.id')
            ->all();

        return array_values($people
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->internalAssignment?->job_title,
                'canViewEmployee' => in_array($employee->id, $withPage, true),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, client: string|null}>
     */
    private static function projects(string $search): array
    {
        return array_values(Project::query()
            ->with('client:id,name')
            ->whereNameContains($search)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->name,
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private static function clients(string $search): array
    {
        return array_values(Client::query()
            ->whereNameContains($search)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all());
    }
}
