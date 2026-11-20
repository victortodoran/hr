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
        ->assertSee('Nobody holds this Role yet.')
        ->click('internal:role=link[name="Payroll"i]')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('2 of 23 Permissions · held by 1 person')
        ->assertSee('View Pay')
        ->assertSee('Ben Brown')
        ->fill('[aria-label="Search Roles"]', 'aud')
        ->assertCount('[data-test="role-item"]', 1)
        ->click('internal:role=link[name="Auditor"i]')
        ->assertPathIs("/roles/{$auditor->id}")
        ->back()
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertSee('Ben Brown')
        ->assertNoJavaScriptErrors();
});

it('edits a Role\'s Permissions and Scopes in place, counting the changes, and saves them', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->assertMissing('[data-slot="save-bar"]')
        ->click('[aria-label="Scope for View Time Off"] >> text=Own')
        ->assertSee('1 unsaved change')
        ->assertSee('Changed')
        ->click('[aria-label="Scope for View Time Off"] >> text=Reports')
        ->click('[aria-label="View Projects"]')
        ->assertSee('2 unsaved changes')
        ->click('Save changes')
        ->assertSee('Role saved.')
        ->assertSee('Saved')
        ->assertMissing('[data-slot="save-bar"]')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertNoJavaScriptErrors();

    expect($payroll->refresh()->grants())->toBe(['view-pay' => 'everyone', 'view-time-off' => 'reports']);
});

it('puts back the Role as saved on Cancel', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->fill('#role-name', 'Pay Team')
        ->click('[aria-label="Scope for View Pay"] >> text=None')
        ->assertSee('2 unsaved changes')
        ->click('Cancel')
        ->assertValue('#role-name', 'Payroll')
        ->assertMissing('[data-slot="save-bar"]')
        ->assertNoJavaScriptErrors();
});

it('adds someone to a Role and removes them, each saved at once, keeping unsaved changes above', function () {
    [, $payroll] = rolesToManage();
    Employee::factory()->create(['name' => 'Cara Lane']);

    visit(route('roles.show', $payroll))
        ->fill('#role-name', 'Pay Team')
        ->click('Add people')
        ->fill('[aria-label="Search Employees to add to Payroll"]', 'cara')
        ->click('internal:role=checkbox')
        ->click('Add 1 person')
        ->assertSee('Cara Lane added to Payroll.')
        ->assertSeeIn('[data-test="role-holders"]', 'Cara Lane')
        ->assertValue('#role-name', 'Pay Team')
        ->click('[aria-label="Remove Ben Brown from Payroll"]')
        ->assertSee('Ben Brown removed from Payroll.')
        ->assertValue('#role-name', 'Pay Team')
        ->assertNoJavaScriptErrors();

    expect($payroll->employees()->pluck('name')->all())->toBe(['Cara Lane'])
        ->and($payroll->refresh()->name)->toBe('Payroll');
});

it('refuses a name another Role already has before saving', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->fill('#role-name', 'Auditor')
        ->assertSee('A Role called Auditor already exists. Choose another name.')
        ->assertButtonDisabled('Save changes')
        ->fill('#role-name', 'Pay Team')
        ->assertButtonEnabled('Save changes')
        ->assertNoJavaScriptErrors();
});

it('makes a Role copying another\'s Permissions, landing on it with a message', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.index'))
        ->click('internal:role=link[name="New Role"s]')
        ->assertPathIs('/roles/create')
        ->assertSee('Untitled Role')
        ->assertButtonDisabled('internal:role=button[name="New Role"s]')
        ->fill('#role-name', 'Pay Leads')
        ->assertSeeIn('nav[aria-label="Roles"]', 'Pay Leads')
        ->click('#role-copy')
        ->click('[role="option"] >> text=Payroll')
        ->assertSee('2 of 23 Permissions')
        ->click('internal:role=button[name="New Role"s]')
        ->assertSee('Pay Leads created')
        ->assertNoJavaScriptErrors();

    $payLeads = Role::query()->where('name', 'Pay Leads')->sole();
    expect($payLeads->grants())->toBe($payroll->grants())
        ->and($payLeads->employees()->exists())->toBeFalse();
});

it('shows Admin\'s Permissions collapsed under a Callout', function () {
    rolesToManage();
    Role::factory()->admin()->create(['name' => 'Admin']);

    visit(route('roles.index'))
        ->assertSee('Admin always holds every Permission, with Scope Everyone.')
        ->assertMissing('[data-test="permission-row"]')
        ->click('Show Permissions')
        ->assertCount('[data-test="permission-row"]', 23)
        ->assertNoJavaScriptErrors();
});

it('asks before leaving unsaved changes, from Cancel on a new Role or the sidebar, and leaves straight away without any', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->click('internal:role=link[name="New Role"s]')
        ->click('Cancel')
        ->assertPathIs("/roles/{$payroll->id}")
        ->fill('#role-name', 'Pay Team')
        ->click('Home')
        ->assertSee('Discard changes?')
        ->assertSee('You have 1 unsaved change to Payroll.')
        ->click('Keep editing')
        ->assertPathIs("/roles/{$payroll->id}")
        ->assertValue('#role-name', 'Pay Team')
        ->click('Home')
        ->click('Discard')
        ->assertPathIs('/home')
        ->assertNoJavaScriptErrors();

    expect($payroll->refresh()->name)->toBe('Payroll');
});

it('changes a Role\'s Scope and saves it on a phone without scrolling sideways', function () {
    [, $payroll] = rolesToManage();

    visit(route('roles.show', $payroll))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="Scope for View Pay"] >> text=Reports')
        ->assertSee('1 unsaved change')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('Save changes')
        ->assertSee('Role saved.')
        ->assertNoJavaScriptErrors();

    expect($payroll->refresh()->grants())->toBe(['view-pay' => 'reports', 'view-projects' => null]);
});
