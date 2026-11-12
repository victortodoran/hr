<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\ApprovalController;
use App\Http\Requests\ApprovalFiltersRequest;
use App\Http\Requests\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, WorkEntryPolicy::class, Employee::class, ApprovalFiltersRequest::class, SelectedWorkEntriesRequest::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * Ben, who approves the Work Entries of his Reports, below Ada at the top.
 */
function approver(Scope $scope = Scope::Reports): Employee
{
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);

    return employeeWith([Permission::ApproveWorkEntries->value => $scope], ['name' => 'Ben', 'manager_id' => $ada->id]);
}

it('lists the Pending Work Entries of the approver\'s Reports, oldest first', function () {
    $ben = approver();
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id]);
    $carasRelaunch = assignmentOf($cara);
    $later = WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-10', 'minutes' => 90, 'description' => 'API']);
    $earlier = WorkEntry::factory()->for(assignmentOf($dev, 'Billing'))->create(['date' => '2026-11-03', 'minutes' => 60, 'description' => 'Invoices']);
    WorkEntry::factory()->for($carasRelaunch)->approved()->create(['date' => '2026-11-02']);
    $outsiders = assignmentOf(Employee::factory()->create(['manager_id' => $ben->manager_id]), 'Archive');
    WorkEntry::factory()->for($outsiders)->create(['date' => '2026-11-04']);

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('approvals/Index')
        ->where('canApprove', true)
        ->where('canReopen', false)
        ->where('canLock', false)
        ->where('filters', ['employee' => null, 'project' => null, 'from' => null, 'to' => null, 'state' => 'pending'])
        ->where('entries.data', [
            ['id' => $earlier->id, 'date' => '2026-11-03', 'employee' => ['id' => $dev->id, 'name' => 'Dev'], 'project' => 'Billing', 'hours' => 1, 'description' => 'Invoices', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_approve' => true, 'can_reopen' => false, 'can_lock' => false],
            ['id' => $later->id, 'date' => '2026-11-10', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'hours' => 1.5, 'description' => 'API', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_approve' => true, 'can_reopen' => false, 'can_lock' => false],
        ])
        ->where('total', 2.5)
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara'], ['id' => $dev->id, 'name' => 'Dev']])
        ->where('projects', [
            ['id' => $outsiders->project_id, 'name' => 'Archive'],
            ['id' => $earlier->assignment->project_id, 'name' => 'Billing'],
            ['id' => $carasRelaunch->project_id, 'name' => 'Relaunch'],
        ]));
});

it('never lists the approver\'s own Work Entries, even when they may approve everyone', function () {
    $ben = approver(Scope::Everyone);
    $ada = Employee::query()->findOrFail($ben->manager_id);
    $adas = WorkEntry::factory()->for(assignmentOf($ada))->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$adas->id])
        ->where('employees', [['id' => $ada->id, 'name' => 'Ada']]));
});

it('refuses the Approvals page to someone who neither approves, reopens nor locks Work Entries', function () {
    $cara = employeeWith([Permission::ManageWorkEntries->value => Scope::Everyone, Permission::ViewWorkEntries->value => Scope::Everyone]);

    actingAs($cara)->get(route('approvals.index'))->assertForbidden();
    actingAs($cara)->post(route('approvals.store'), ['ids' => []])->assertForbidden();
    actingAs($cara)->delete(route('approvals.destroy'), ['ids' => []])->assertForbidden();
    actingAs($cara)->post(route('locks.store'), ['ids' => []])->assertForbidden();
});

it('filters by Employee, Project, dates and state, keeping the total to what matches', function (array $filters, array $expected) {
    $ben = approver();
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $ben->id]);
    $carasRelaunch = assignmentOf($cara);
    $entries = [
        'cara relaunch 3rd' => WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-03', 'minutes' => 60]),
        'cara relaunch 10th approved' => WorkEntry::factory()->for($carasRelaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 120]),
        'cara billing 5th' => WorkEntry::factory()->for(assignmentOf($cara, 'Billing'))->create(['date' => '2026-11-05', 'minutes' => 180]),
        'dev relaunch 9th' => WorkEntry::factory()->for(assignmentOf($dev, 'Relaunch'))->create(['date' => '2026-11-09', 'minutes' => 240]),
    ];
    $query = collect($filters)->map(fn (mixed $value): mixed => match ($value) {
        'cara' => $cara->id,
        'relaunch' => $carasRelaunch->project_id,
        default => $value,
    })->all();

    $response = actingAs($ben)->get(route('approvals.index', $query));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => null, 'project' => null, 'from' => null, 'to' => null, 'state' => 'pending', ...$query])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === collect($expected)->map(fn (string $name): int => $entries[$name]->id)->all())
        ->where('total', collect($expected)->sum(fn (string $name): int => $entries[$name]->minutes) / 60));
})->with([
    'Employee' => [['employee' => 'cara'], ['cara relaunch 3rd', 'cara billing 5th']],
    'Project' => [['project' => 'relaunch'], ['cara relaunch 3rd']],
    'from' => [['from' => '2026-11-04'], ['cara billing 5th', 'dev relaunch 9th']],
    'to' => [['to' => '2026-11-05'], ['cara relaunch 3rd', 'cara billing 5th']],
    'state' => [['state' => 'approved'], ['cara relaunch 10th approved']],
]);

it('treats empty filters as no filter', function () {
    $ben = approver();

    $response = actingAs($ben)->get(route('approvals.index', ['employee' => '', 'project' => '', 'from' => '', 'to' => '', 'state' => '']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => null, 'project' => null, 'from' => null, 'to' => null, 'state' => 'pending']));
});

it('refuses malformed filters', function (array $filters, string $field, string $message) {
    $ben = approver();

    actingAs($ben)->get(route('approvals.index', $filters))->assertInvalid([$field => $message]);
})->with([
    'Employee not a number' => [['employee' => 'Cara'], 'employee', 'The employee field must be an integer.'],
    'Project not a number' => [['project' => 'Relaunch'], 'project', 'The project field must be an integer.'],
    'from not a date' => [['from' => 'soon'], 'from', 'The from field must match the format Y-m-d.'],
    'to not a date' => [['to' => 'later'], 'to', 'The to field must match the format Y-m-d.'],
    'to before from' => [['from' => '2026-11-05', 'to' => '2026-11-04'], 'to', 'The to field must be a date after or equal to from.'],
    'unknown state' => [['state' => 'invoiced'], 'state', 'The selected state is invalid.'],
]);

it('pages through 25 Work Entries at a time, keeping the filters', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    WorkEntry::factory()->for($relaunch)->count(26)->create(['date' => '2026-11-10', 'minutes' => 15]);

    $response = actingAs($ben)->get(route('approvals.index', ['from' => '2026-11-01']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('entries.data', 25)
        ->where('entries.total', 26)
        ->where('entries.next_page_url', route('approvals.index', ['from' => '2026-11-01', 'page' => 2]))
        ->where('total', 6.5));
});

it('gives every matching Work Entry\'s ID when asked, for selecting them all', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $ids = WorkEntry::factory()->for($relaunch)->count(26)->create(['date' => '2026-11-10'])->pluck('id')->all();
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);

    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('matchingIds'));
    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('matchingIds', fn (Assert $reload) => $reload->where('matchingIds', $ids)));
});

it('approves the selected Pending Work Entries, recording who approved them and when', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $first = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09']);
    $second = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $untouched = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->from(route('approvals.index', ['from' => '2026-11-01']))
        ->post(route('approvals.store'), ['ids' => [(string) $first->id, (string) $second->id]]);

    $response->assertRedirect(route('approvals.index', ['from' => '2026-11-01']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Work Entries approved.']);
    foreach ([$first, $second] as $workEntry) {
        $workEntry->refresh();
        expect($workEntry->state)->toBe(WorkEntryState::Approved)
            ->and($workEntry->approved_by)->toBe($ben->id)
            ->and($workEntry->approved_at?->toDateTimeString())->toBe('2026-11-11 09:00:00');
    }
    expect($untouched->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('approves only the selected Work Entries it may, counting each once, and says how many it skipped', function () {
    $ben = approver(Scope::Everyone);
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $locked = WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-10']);
    $approvedBy = $approved->approved_by;

    $response = actingAs($ben)->post(route('approvals.store'), ['ids' => [$pending->id, $pending->id, $own->id, $approved->id, $locked->id, WorkEntry::query()->max('id') + 1]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry approved, 4 skipped.']);
    expect($pending->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($own->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($approved->refresh()->approved_by)->toBe($approvedBy)
        ->and($locked->refresh()->state)->toBe(WorkEntryState::Locked);
});

it('skips Work Entries of Employees outside the approver\'s Scope', function () {
    $ben = approver();
    $outside = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->post(route('approvals.store'), ['ids' => [$outside->id]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries approved, 1 skipped.']);
    expect($outside->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('refuses a selection that is not a list of Work Entry IDs', function (mixed $ids, string $field, string $message) {
    $ben = approver();

    actingAs($ben)->post(route('approvals.store'), ['ids' => $ids])->assertInvalid([$field => $message]);
})->with([
    'missing' => [null, 'ids', 'Select at least one Work Entry.'],
    'empty' => [[], 'ids', 'Select at least one Work Entry.'],
    'not a list' => ['all', 'ids', 'Select at least one Work Entry.'],
    'not numbers' => [['first'], 'ids.0', 'The ids.0 field must be an integer.'],
]);

it('shows the Approvals item in the navigation only to those who approve Work Entries', function (array $grants, bool $shown) {
    $ben = employeeWith($grants);

    $response = actingAs($ben)->get(route('profile.edit'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.canSeeApprovals', $shown));
})->with([
    'approve reports' => [[Permission::ApproveWorkEntries->value => Scope::Reports], true],
    'manage everyone' => [[Permission::ManageWorkEntries->value => Scope::Everyone], false],
]);
