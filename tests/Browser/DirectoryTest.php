<?php

use App\Enums\Permission;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

it('opens a colleague\'s Reporting Chain in a sheet over the Directory, follows it up, and closes it', function () {
    $ada = Employee::factory()->create(['name' => 'Ada Top']);
    $ben = Employee::factory()->reportingTo($ada)->create(['name' => 'Ben Manager']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report']);

    actingAs(employeeWith([Permission::ViewDirectory->value => null], ['name' => 'Zoe Viewer']));

    visit(route('directory.index'))
        ->click('main [data-slot="data-row"]:has-text("Cara Report")')
        ->assertQueryStringHas('person')
        ->assertSeeIn('[data-slot="record-sheet"]', 'Reporting Chain')
        ->assertSeeIn('[data-slot="record-sheet"]', 'Ben Manager')
        ->click('[data-slot="record-sheet"] >> internal:role=link[name="Ada Top"s]')
        ->assertQueryStringHas('person', (string) $ada->id)
        ->assertSeeIn('[data-slot="record-sheet"] h2', 'Ada Top')
        ->keys('[data-slot="record-sheet"]', 'Escape')
        ->assertQueryStringMissing('person')
        ->assertMissing('[data-slot="record-sheet"]')
        ->assertNoJavaScriptErrors();
});

it('opens a sheet on a phone from an old link to someone\'s Reporting Chain, fitting the screen', function () {
    $ben = Employee::factory()->create(['name' => 'Ben Manager']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report']);

    actingAs(employeeWith([Permission::ViewDirectory->value => null]));

    visit(route('directory.show', $cara))
        ->resize(390, 844)
        ->assertQueryStringHas('person', (string) $cara->id)
        ->assertSeeIn('[data-slot="record-sheet"]', 'Ben Manager')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->keys('[data-slot="record-sheet"]', 'Escape')
        ->click('main [data-slot="data-row"]:has-text("Ben Manager")')
        ->assertSeeIn('[data-slot="record-sheet"]', 'Cara Report')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
