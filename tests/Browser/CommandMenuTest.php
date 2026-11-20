<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Search\CommandMenuController;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;

use function Pest\Laravel\actingAs;

covers(CommandMenuController::class);

/** The command menu's results, group label by group label, as one line. */
const COMMAND_MENU_GROUPS = <<<'JS'
    [...document.querySelectorAll('[data-test="command-menu"] [role="group"]')]
        .map((group) => group.firstElementChild.textContent.trim()).join(' · ')
    JS;

it('opens with Ctrl+K, finds a Report and opens their Employee page', function () {
    $ben = managerBen();
    giveRole($ben, [Permission::ViewEmployeeRecords->value => Scope::Reports]);
    $dan = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan Report']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Dan Elsewhere']);

    actingAs($ben);

    visit(route('time-off.index'))
        ->keys('[data-slot="command-menu"]', 'Control+k')
        ->assertVisible('[data-test="command-menu"]')
        ->assertScript(COMMAND_MENU_GROUPS, 'Pages')
        ->fill('[data-test="command-menu"] input', 'dan')
        ->assertSeeIn('[data-test="command-menu"]', 'Dan Report')
        ->assertDontSeeIn('[data-test="command-menu"]', 'Dan Elsewhere')
        ->click('[data-test="command-menu"] [role="option"]:has-text("Dan Report")')
        ->assertPathIs(route('employees.show', $dan, false))
        ->assertMissing('[data-test="command-menu"]')
        ->assertNoJavaScriptErrors();
});

it('opens from the top bar\'s search button and lists people, Projects, Clients and pages in that order', function () {
    $ana = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewProjects->value => null, Permission::ManageClients->value => null], ['name' => 'Ana Admin']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Ola Pro']);
    $project = Project::factory()->forClient(Client::factory()->create(['name' => 'Pronto']))->create(['name' => 'Prolog']);

    actingAs($ana);

    visit(route('landing'))
        ->click('[data-test="command-menu-button"]')
        ->fill('[data-test="command-menu"] input', 'pro')
        ->assertSeeIn('[data-test="command-menu"]', 'Prolog')
        ->assertScript(COMMAND_MENU_GROUPS, 'People · Projects · Clients · Pages')
        ->click('[data-test="command-menu"] [role="option"]:has-text("Prolog")')
        ->assertPathIs(route('projects.show', $project, false))
        ->assertNoJavaScriptErrors();
});

it('offers only the sidebar\'s pages and says when nothing matches', function () {
    actingAs(Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']));

    visit(route('landing'))
        ->keys('[data-slot="command-menu"]', 'Control+k')
        ->assertDontSeeIn('[data-test="command-menu"]', 'Employees')
        ->fill('[data-test="command-menu"] input', 'time')
        ->click('[data-test="command-menu"] [role="option"]:has-text("Time Off")')
        ->assertPathIs(route('time-off.index', absolute: false))
        ->keys('[data-slot="command-menu"]', 'Control+k')
        ->fill('[data-test="command-menu"] input', 'zzz')
        ->assertSeeIn('[data-test="command-menu"]', 'Nothing matches “zzz”.')
        ->keys('[data-test="command-menu"] input', 'Escape')
        ->assertMissing('[data-test="command-menu"]')
        ->assertNoJavaScriptErrors();
});

it('finds a colleague from the search icon on a phone and opens them in the Directory', function () {
    $cara = employeeWith([Permission::ViewDirectory->value => null], ['name' => 'Cara Lane']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Carl Moss']);

    actingAs($cara);

    visit(route('landing'))
        ->resize(390, 844)
        ->click('[data-test="command-menu-icon"]')
        ->fill('[data-test="command-menu"] input', 'carl')
        ->assertScript('document.querySelector(\'[data-test="command-menu"]\').getBoundingClientRect().width <= window.innerWidth', true)
        ->click('[data-test="command-menu"] [role="option"]:has-text("Carl Moss")')
        ->assertPathBeginsWith('/directory')
        ->assertSee('Carl Moss')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
