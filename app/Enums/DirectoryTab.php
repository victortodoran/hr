<?php

namespace App\Enums;

use App\Models\Employee;

/**
 * The tabs of the Directory page: everyone currently working at the
 * company, and the viewer's Direct Reports.
 */
enum DirectoryTab: string
{
    case Everyone = 'everyone';
    case Reports = 'reports';

    /**
     * The tabs the viewer may open, in order: Everyone needs view directory,
     * Reports needs Direct Reports.
     *
     * @return list<self>
     */
    public static function openableBy(Employee $viewer): array
    {
        return array_values(array_filter([
            $viewer->holds(Permission::ViewDirectory) ? self::Everyone : null,
            $viewer->can('viewTeam', Employee::class) ? self::Reports : null,
        ]));
    }
}
