<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Access\RoleController;
use App\Models\Employee;
use App\Models\Role;

use function Pest\Laravel\actingAs;

covers(RoleController::class);

/**
 * Someone who manages Roles, through a Role called "Role Managers", next to
 * a Payroll Role held by Ben and an Auditor Role held by nobody.
 *
 * @return array{Employee, Role, Role}
 */
function rolesToManage(): array
{
    $ada = employeeWhoManagesRoles('Role Managers');
    $payroll = Role::factory()->withPermissions([Permission::ViewPay->value => Scope::Everyone, Permission::ViewProjects->value => null])->create(['name' => 'Payroll']);
    $payroll->employees()->attach(Employee::factory()->create(['name' => 'Ben Brown']));
    $auditor = Role::factory()->create(['name' => 'Auditor']);

    actingAs($ada);

    return [$ada, $payroll, $auditor];
}

it('selects and searches Roles, keeping the selected one in the URL', function () {
    [, $payroll, $auditor] = rolesToManage();

    visit(route('roles.index'))
        ->assertPathIs('/roles')
        ->assertSee('Auditor')
        ->assertSee('Nobody holds this Role yet.')
        ->click('Payroll')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('2 permissions · held by 1 person')
        ->assertSee('View pay')
        ->assertSee('Ben Brown')
        ->fill('[aria-label="Search roles"]', 'aud')
        ->assertCount('[data-test="role-item"]', 1)
        ->click('Auditor')
        ->assertPathIs("/roles/{$auditor->id}")
        ->back()
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('Ben Brown')
        ->assertNoJavaScriptErrors();
});

it('edits a Role\'s Permissions and Scopes in place, counting the changes, and saves them', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->click('Edit')
        ->assertPathIs("/roles/{$payroll->id}/edit")
        ->assertSee('Editing…')
        ->assertSee('0 unsaved changes')
        ->click('View time off')
        ->assertSee('1 unsaved change')
        ->click('[aria-label="Scope for View time off"] >> text=Reports')
        ->click('View projects')
        ->assertSee('2 unsaved changes')
        ->click('Save changes')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('Role saved.')
        ->assertNoJavaScriptErrors();

    expect($payroll->refresh()->grants())->toBe(['view-pay' => 'everyone', 'view-time-off' => 'reports']);
});

it('adds someone to a Role while editing it with Enter in the people search, saving them with the Role', function () {
    [, $payroll] = rolesToManage();
    Employee::factory()->create(['name' => 'Cara Lane']);

    visit(route('roles.edit', $payroll))
        ->fill('[aria-label="Add an Employee"]', 'cara')
        ->keys('[aria-label="Add an Employee"]', 'Enter')
        ->assertPathIs("/roles/{$payroll->id}/edit")
        ->assertSee('Will be added')
        ->assertSee('1 unsaved change')
        ->click('Save changes')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('Cara Lane')
        ->assertNoJavaScriptErrors();

    expect($payroll->employees()->orderBy('name')->pluck('name')->all())->toBe(['Ben Brown', 'Cara Lane']);
});

it('refuses a name another Role already has before saving', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.edit', $payroll))
        ->fill('#role-name', 'Auditor')
        ->assertSee('A Role called Auditor already exists. Choose another name.')
        ->assertButtonDisabled('Save changes')
        ->fill('#role-name', 'Pay Team')
        ->assertButtonEnabled('Save changes')
        ->assertNoJavaScriptErrors();
});

it('creates a Role copying another\'s Permissions, landing on it with a message', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.index'))
        ->click('New Role')
        ->assertPathIs('/roles/create')
        ->assertSee('Untitled Role')
        ->assertButtonDisabled('Create Role')
        ->assertSee('Give the Role a name to create it.')
        ->fill('#role-name', 'Pay Leads')
        ->assertSeeIn('nav[aria-label="Roles"]', 'Pay Leads')
        ->click('#role-copy')
        ->click('[role="option"] >> text=Payroll')
        ->assertSee('2 of 20')
        ->click('Create Role')
        ->assertSee('Pay Leads created')
        ->assertNoJavaScriptErrors();

    $payLeads = Role::query()->where('name', 'Pay Leads')->sole();
    expect($payLeads->grants())->toBe($payroll->grants())
        ->and($payLeads->employees()->exists())->toBeFalse();
});

it('asks before leaving unsaved changes, from Cancel or the sidebar, and leaves straight away without any', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.edit', $payroll))
        ->click('Cancel')
        ->assertPathIs("/roles/{$payroll->id}")
        ->click('New Role')
        ->click('Cancel')
        ->assertPathIs("/roles/{$payroll->id}")
        ->click('Edit')
        ->fill('#role-name', 'Pay Team')
        ->click('Cancel')
        ->assertSee('Discard changes?')
        ->assertSee('You have 1 unsaved change to Payroll.')
        ->click('Keep editing')
        ->assertPathIs("/roles/{$payroll->id}/edit")
        ->assertValue('#role-name', 'Pay Team')
        ->click('Home')
        ->assertSee('Discard changes?')
        ->click('Discard')
        ->assertPathIs('/home')
        ->assertNoJavaScriptErrors();

    expect($payroll->refresh()->name)->toBe('Payroll');
});

it('fits the Roles page on a phone without scrolling sideways', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.edit', $payroll))
        ->resize(390, 844)
        ->assertSee('Save changes')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
