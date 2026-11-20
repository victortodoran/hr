<?php

namespace App\Pages\Employees;

use App\Enums\Permission;
use App\Models\Employee;
use App\Support\ReportingChain;

/**
 * The person open in a sheet over the Directory (rule K8), as the props it
 * renders: where they sit in the company.
 */
final class DirectoryPage
{
    /**
     * The asked-for Employee's Reporting Chain: everyone above them up to
     * one with no Manager, and the Employees they are the Manager of. Null
     * when nobody is asked for, when the viewer lacks view directory, and
     * for a Former Employee, who has left the directory.
     *
     * @return array{person: array<string, mixed>|null}
     */
    public static function props(Employee $actor, ?int $personId): array
    {
        $person = $personId !== null && $actor->can(Permission::ViewDirectory->value)
            ? Employee::query()->notFormer()->find($personId)
            : null;

        return [
            'person' => $person === null ? null : ReportingChain::of($person, $actor),
        ];
    }
}
