<?php

namespace App\Pages\Search;

use App\Enums\Permission;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;

/**
 * The command menu's search results as the props it renders: the people,
 * Projects and Clients whose name holds the search, only those the viewer
 * could already open from the app, at most five of each.
 */
final class CommandMenuResults
{
    /** How many results each group shows at most. */
    public const int LIMIT = 5;

    /**
     * Each group's matches by name. People leave out Former Employees and
     * keep those whose Employee page the viewer may open, plus, for viewers
     * with view directory, everyone the Directory lists; each says whether it
     * opens on their Employee page or in the Directory. Projects need seeing
     * the Projects, Clients managing them. An empty search finds nothing.
     *
     * @return array{people: list<array{id: int, name: string, job_title: string|null, canViewEmployee: bool}>, projects: list<array{id: int, name: string, client: string|null}>, clients: list<array{id: int, name: string}>}
     */
    public static function props(Employee $viewer, string $search): array
    {
        if (trim($search) === '') {
            return ['people' => [], 'projects' => [], 'clients' => []];
        }

        $pattern = '%'.addcslashes(trim($search), '\\%_').'%';

        return [
            'people' => self::people($viewer, $pattern),
            'projects' => $viewer->can('viewAny', Project::class) ? self::projects($pattern) : [],
            'clients' => $viewer->can(Permission::ManageClients->value) ? self::clients($pattern) : [],
        ];
    }

    /**
     * @return list<array{id: int, name: string, job_title: string|null, canViewEmployee: bool}>
     */
    private static function people(Employee $viewer, string $pattern): array
    {
        $seesDirectory = $viewer->holds(Permission::ViewDirectory);

        return array_values(Employee::query()
            ->notFormer()
            ->where(fn (EmployeeBuilder $openable) => $openable
                ->whereIn('employees.id', Employee::query()->withPageVisibleTo($viewer)->select('employees.id'))
                ->when($seesDirectory, fn (EmployeeBuilder $listed) => $listed
                    ->orWhereIn('employees.id', Employee::query()->current()->select('employees.id'))))
            ->whereLike('name', $pattern)
            ->with('internalAssignment:id,employee_id,project_id,job_title')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->internalAssignment?->job_title,
                'canViewEmployee' => $viewer->can('view', $employee),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, client: string|null}>
     */
    private static function projects(string $pattern): array
    {
        return array_values(Project::query()
            ->with('client:id,name')
            ->whereLike('name', $pattern)
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
    private static function clients(string $pattern): array
    {
        return array_values(Client::query()
            ->whereLike('name', $pattern)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all());
    }
}
