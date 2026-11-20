<?php

namespace App\Pages\Employees;

use App\Enums\DirectoryTab;
use App\Models\Employee;

/**
 * The Directory page as the props it renders: which tab is open, the tabs
 * the viewer may open, and only the open tab's rows (rule K4).
 */
final class DirectoryIndexPage
{
    /**
     * @param  list<DirectoryTab>  $tabs
     * @return array<string, mixed>
     */
    public static function props(Employee $viewer, DirectoryTab $tab, array $tabs, string $search): array
    {
        return [
            'tab' => $tab,
            'tabs' => $tabs,
            ...match ($tab) {
                DirectoryTab::Everyone => DirectoryListPage::props($search),
                DirectoryTab::Reports => TeamPage::props($viewer),
            },
        ];
    }
}
