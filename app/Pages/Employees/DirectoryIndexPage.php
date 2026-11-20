<?php

namespace App\Pages\Employees;

use App\Enums\DirectoryTab;
use App\Enums\Permission;
use App\Models\Employee;
use App\Support\ReportingChain;

/** Only the open tab's rows are loaded (rule K4). */
final class DirectoryIndexPage
{
    /**
     * @param  list<DirectoryTab>  $tabs
     * @return array<string, mixed>
     */
    public static function props(Employee $viewer, DirectoryTab $tab, array $tabs, string $search, ?int $personId): array
    {
        return [
            'tab' => $tab,
            'tabs' => $tabs,
            ...match ($tab) {
                DirectoryTab::Everyone => DirectoryListPage::props($search),
                DirectoryTab::Reports => TeamPage::props($viewer),
            },
            'person' => self::person($viewer, $personId),
        ];
    }

    /**
     * Null for a Former Employee, who has left the directory.
     *
     * @return array<string, mixed>|null
     */
    private static function person(Employee $viewer, ?int $personId): ?array
    {
        if ($personId === null || ! $viewer->holds(Permission::ViewDirectory)) {
            return null;
        }

        $person = Employee::query()->notFormer()->find($personId);

        return $person === null ? null : ReportingChain::of($person, $viewer);
    }
}
