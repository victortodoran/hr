<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryDeclineController;
use App\Http\Controllers\WorkEntries\WorkEntryListController;
use App\Http\Requests\WorkEntries\CopyWorkEntriesRequest;
use App\Http\Requests\WorkEntries\DeclineWorkEntriesRequest;
use App\Http\Requests\WorkEntries\WorkEntryRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(
    WorkEntryDeclineController::class,
    DeclineWorkEntriesRequest::class,
    WorkEntry::class,
    WorkEntryPolicy::class,
    WorkEntryController::class,
    WorkEntryListController::class,
    HomeController::class,
    CopyWorkEntriesRequest::class,
    WorkEntryRequest::class,
);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

/** @return array{ben: Employee, cara: Employee} */
function benAndCara(): array
{
    $ben = managerBen();

    return ['ben' => $ben, 'cara' => recorder(['manager_id' => $ben->id])];
}

it('declines the selected Pending Work Entries with one reason, recording who declined them and when', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    $first = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09']);
    $second = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $untouched = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->from(route('approvals.index'))
        ->post(route('approvals.declines.store'), ['ids' => [(string) $first->id, (string) $second->id], 'decline_reason' => 'Interviews go on Internal, not Acme']);

    $response->assertRedirect(route('approvals.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Work Entries declined.']);
    foreach ([$first, $second] as $workEntry) {
        $workEntry->refresh();
        expect($workEntry->state)->toBe(WorkEntryState::Declined)
            ->and($workEntry->declined_by)->toBe($ben->id)
            ->and($workEntry->declined_at?->toDateTimeString())->toBe('2026-11-13 09:00:00')
            ->and($workEntry->decline_reason)->toBe('Interviews go on Internal, not Acme');
    }
    expect($untouched->refresh()->state)->toBe(WorkEntryState::Pending);
    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$untouched->id]));
});

it('declines only Pending Work Entries of the decliner\'s Direct Reports, never their own, and says how many it skipped', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $below = WorkEntry::factory()->for(assignmentOf(Employee::factory()->reportingTo($cara)->create()))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->post(route('approvals.declines.store'), [
        'ids' => [$pending->id, $pending->id, $own->id, $approved->id, $below->id],
        'decline_reason' => 'Wrong Project',
    ]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry declined, 3 skipped.']);
    expect($pending->refresh()->state)->toBe(WorkEntryState::Declined)
        ->and($own->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($approved->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($below->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('lets the next Manager up decline for a Manager who has left', function () {
    ['ben' => $ben] = benAndCara();
    $fay = Employee::factory()->former()->reportingTo($ben)->create();
    $pending = WorkEntry::factory()->for(assignmentOf(Employee::factory()->reportingTo($fay)->create()))->create(['date' => '2026-11-10']);

    actingAs($ben)->post(route('approvals.declines.store'), ['ids' => [$pending->id], 'decline_reason' => 'Wrong Project']);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Declined);
});

it('gives declining to no Role, not even one that grants reopening for everyone', function () {
    ['cara' => $cara] = benAndCara();
    $pending = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    $dan = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone, Permission::ManageWorkEntries->value => Scope::Everyone]);
    $eve = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone]);
    Employee::factory()->reportingTo($eve)->create();

    actingAs($dan)->post(route('approvals.declines.store'), ['ids' => [$pending->id], 'decline_reason' => 'Wrong Project'])->assertForbidden();
    actingAs($eve)->post(route('approvals.declines.store'), ['ids' => [$pending->id], 'decline_reason' => 'Wrong Project'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries declined, 1 skipped.']);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('refuses to decline without a reason or a selection', function (array $form, string $field, string $message) {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $pending = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);

    actingAs($ben)->post(route('approvals.declines.store'), [...['ids' => [$pending->id], 'decline_reason' => 'Wrong Project'], ...$form])
        ->assertInvalid([$field => $message]);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Pending);
})->with([
    'no reason' => [['decline_reason' => ''], 'decline_reason', 'Give a reason for declining.'],
    'no selection' => [['ids' => []], 'ids', 'Select at least one Work Entry.'],
]);

it('shows a Declined Work Entry with who declined it and why, open to change or delete, its hours left out of the week\'s totals', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    $internal = assignmentOf($cara, 'Internal');
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 300]);
    $declined = WorkEntry::factory()->for($internal)->declined($ben, 'Interviews go on Internal, not Acme')->create(['date' => '2026-11-10', 'minutes' => 90]);

    $week = actingAs($cara)->get(route('work-entries.index'));

    $week->assertInertia(fn (Assert $page) => $page
        ->where('total', 5)
        ->where('declinedHours', 1.5)
        ->where('assignmentTotals', [['assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 5]])
        ->where('weeks.0.hours', 5)
        ->where('days.1.hours', 5)
        ->where('days.1.entries.1', fn (Collection $entry): bool => $entry->only('id', 'state', 'can_edit', 'can_delete', 'declined')->all() === [
            'id' => $declined->id,
            'state' => 'declined',
            'can_edit' => true,
            'can_delete' => true,
            'declined' => ['by' => 'Ben', 'reason' => 'Interviews go on Internal, not Acme'],
        ]));
});

it('sends a changed Declined Work Entry back to Pending without its reason', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $internal = assignmentOf($cara, 'Internal');
    $declined = WorkEntry::factory()->for(assignmentOf($cara))->declined($ben)->create(['date' => '2026-11-10']);

    actingAs($cara)->patch(route('work-entries.update', $declined), workEntryForm($internal, ['hours' => '1.5', 'description' => 'Interviews']))
        ->assertSessionHasNoErrors();

    $declined->refresh();
    expect($declined->state)->toBe(WorkEntryState::Pending)
        ->and($declined->assignment_id)->toBe($internal->id)
        ->and($declined->declined_by)->toBeNull()
        ->and($declined->declined_at)->toBeNull()
        ->and($declined->decline_reason)->toBeNull();
});

it('lets a Declined Work Entry be deleted', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $declined = WorkEntry::factory()->for(assignmentOf($cara))->declined($ben)->create(['date' => '2026-11-10']);

    actingAs($cara)->delete(route('work-entries.destroy', $declined))->assertRedirect();

    expect($declined->fresh())->toBeNull();
});

it('leaves Declined hours out of the 24 a day can hold', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->declined($ben)->create(['date' => '2026-11-10', 'minutes' => 20 * 60]);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['hours' => '8']))
        ->assertSessionHasNoErrors();
});

it('copies no Declined Work Entries, and doesn\'t copy from a day with only Declined ones', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Checkout API']);
    WorkEntry::factory()->for($relaunch)->declined($ben)->create(['date' => '2026-11-10', 'description' => 'Interviews']);
    WorkEntry::factory()->for($relaunch)->declined($ben)->create(['date' => '2026-11-11', 'description' => 'Interviews']);

    actingAs($cara)->get(route('work-entries.index'))
        ->assertInertia(fn (Assert $page) => $page->where('days.3.copy_from', '2026-11-10'));
    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-12'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 1 Work Entry from Tue 10.']);

    expect(WorkEntry::query()->whereDate('date', '2026-11-12')->pluck('description')->all())->toBe(['Checkout API']);
});

it('copies onto a day whose only Work Entries are Declined', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Checkout API']);
    WorkEntry::factory()->for($relaunch)->declined($ben)->create(['date' => '2026-11-12', 'description' => 'Interviews']);

    actingAs($cara)->get(route('work-entries.index'))
        ->assertInertia(fn (Assert $page) => $page->where('days.3.copy_from', '2026-11-11'));
    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-12'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 1 Work Entry from Wed 11.']);

    expect(WorkEntry::query()->whereDate('date', '2026-11-12')->pluck('description')->sort()->values()->all())->toBe(['Checkout API', 'Interviews']);
});

it('filters the Work Entries page by Declined, leaving Declined hours out of its total', function () {
    ['ben' => $ben, 'cara' => $cara] = benAndCara();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 120]);
    $declined = WorkEntry::factory()->for($relaunch)->declined($ben, 'Wrong Project')->create(['date' => '2026-11-10', 'minutes' => 60]);
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);

    actingAs($dan)->get(route('work-entry-list.index'))
        ->assertInertia(fn (Assert $page) => $page->where('total', 2)->where('declinedTotal', 1));
    actingAs($dan)->get(route('work-entry-list.index', ['state' => 'declined']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 0)
            ->where('declinedTotal', 1)
            ->where('entries.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['declined']])->all() === [
                [$declined->id, ['by' => 'Ben', 'reason' => 'Wrong Project']],
            ]));
});
