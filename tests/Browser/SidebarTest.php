<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CompanySettings;
use App\Models\Employee;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(HandleInertiaRequests::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/** The sidebar's link names, group by group. */
const SIDEBAR_NAMES = <<<'JS'
    JSON.stringify([...document.querySelectorAll('[data-sidebar="group"]')]
        .map((group) => [...group.querySelectorAll('[data-sidebar="menu-button"]')]
            .map((link) => link.textContent.trim())))
    JS;

it('lists each area once and moves between Mine and the wider list with the Scope switch', function () {
    CompanySettings::factory()->create();
    $ben = managerBen();
    giveRole($ben, [Permission::ManageWorkEntries->value => Scope::Own, Permission::ViewWorkEntries->value => Scope::Everyone, Permission::ViewTimeOff->value => Scope::Reports, Permission::ViewDirectory->value => null]);
    Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);

    actingAs($ben);

    visit(route('work-entries.index'))
        ->assertScript(SIDEBAR_NAMES, json_encode([
            ['Home', 'Approvals'],
            ['Work', 'Time Off', 'Travel', 'Performance'],
            ['Directory'],
        ]))
        ->assertScript('[...document.querySelectorAll(\'[data-slot="scope-switch"] a\')].map((link) => link.textContent.trim()).join(" · ")', 'Mine · Everyone')
        ->click('internal:role=link[name="Everyone"s]')
        ->assertPathIs(route('work-entry-list.index', absolute: false))
        ->assertAttribute('internal:role=link[name="Everyone"s]', 'aria-current', 'page')
        ->assertScript('[...document.querySelectorAll(\'[data-sidebar="menu-button"][data-active="true"]\')].map((link) => link.textContent.trim()).join()', 'Work')
        ->navigate(route('time-off.index'))
        ->assertScript('[...document.querySelectorAll(\'[data-slot="scope-switch"] a\')].map((link) => link.textContent.trim()).join(" · ")', 'Mine · Reports')
        ->navigate(route('travel-requests.index'))
        ->assertSee('Request travel')
        ->assertMissing('[data-slot="scope-switch"]')
        ->assertNoJavaScriptErrors();
});

it('opens the menu from the top bar on a phone, which names the page', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);

    actingAs($cara);

    visit(route('time-off.index'))
        ->resize(390, 844)
        ->assertSeeIn('[data-test="top-bar-title"]', 'Time Off')
        ->assertMissing('[data-slot="scope-switch"]')
        ->click('[data-sidebar="trigger"]')
        ->click('internal:role=link[name="Performance"s]')
        ->assertPathIs(route('my-performance-evaluations.index', absolute: false))
        ->assertSeeIn('[data-test="top-bar-title"]', 'Performance')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

it('shows a Manager their Direct Reports on the Directory\'s Reports tab on a phone', function () {
    $ben = managerBen();
    giveRole($ben, [Permission::ViewDirectory->value => null]);
    Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Report', 'email' => 'cara.report.with.a.long.address@acme.test']);

    actingAs($ben);

    visit(route('directory.index'))
        ->resize(390, 844)
        ->click('internal:role=tab[name="Reports"s]')
        ->assertQueryStringHas('tab', 'reports')
        ->assertSee('Cara Report')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
