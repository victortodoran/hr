<?php

namespace App\Pages\Employees;

use App\Enums\DirectoryTab;
use App\Enums\Permission;
use App\Models\Employee;
use App\Support\ReportingChain;

/**
 * The Directory page as the props it renders: which tab is open, the tabs
 * the viewer may open, only the open tab's rows (rule K4), and the person
 * open in a sheet over the list (rule K8).
 */
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
     * The asked-for Employee's Reporting Chain: everyone above them up to
     * one with no Manager, and the Employees they are the Manager of. Null
     * when nobody is asked for, when the viewer lacks view directory, and
     * for a Former Employee, who has left the directory.
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
