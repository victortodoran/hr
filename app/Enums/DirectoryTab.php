<?php

namespace App\Enums;

use App\Models\Employee;

enum DirectoryTab: string
{
    case Everyone = 'everyone';
    case Reports = 'reports';

    /** @return list<self> */
    public static function openableBy(Employee $viewer): array
    {
        return array_values(array_filter([
            $viewer->holds(Permission::ViewDirectory) ? self::Everyone : null,
            $viewer->can('viewTeam', Employee::class) ? self::Reports : null,
        ]));
    }
}
