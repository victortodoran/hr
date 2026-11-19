<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(HandleInertiaRequests::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('gives Me and Manage the same short names, told apart by the group title', function () {
    $ben = managerBen();
    giveRole($ben, [Permission::ManageWorkEntries->value => Scope::Own, Permission::ViewWorkEntries->value => Scope::Everyone, Permission::ViewTimeOff->value => Scope::Reports, Permission::ViewTravel->value => Scope::Everyone]);
    Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);

    actingAs($ben);

    visit(route('home'))
        ->assertScript(<<<'JS'
            (() => {
                const group = (label) => [...document.querySelectorAll('[data-sidebar="group"]')]
                    .find((element) => element.querySelector('[data-sidebar="group-label"]')?.textContent.trim() === label);
                const names = (label) => [...group(label).querySelectorAll('[data-sidebar="menu-button"]')]
                    .map((link) => link.textContent.trim());

                return JSON.stringify({ me: names('Me'), manage: names('Manage') });
            })()
            JS, json_encode([
            'me' => ['Home', 'Profile', 'Work', 'Time Off', 'Travel', 'Performance'],
            'manage' => ['Team', 'Approvals', 'Work', 'Time Off', 'Travel', 'Performance'],
        ]))
        ->click('internal:role=link[name="Performance"s] >> nth=1')
        ->assertPathIs(route('performance-evaluations.index', absolute: false))
        ->assertNoJavaScriptErrors();
});
