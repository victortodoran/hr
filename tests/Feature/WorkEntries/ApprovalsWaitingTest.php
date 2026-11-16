<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(HandleInertiaRequests::class, Employee::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * Ben, below Ada at the top, with Cara reporting to him and Dev reporting
 * to Cara.
 *
 * @return array{ada: Employee, ben: Employee, cara: Employee, dev: Employee}
 */
function waitingTeam(): array
{
    $ada = Employee::factory()->create(['name' => 'Ada']);
    $ben = Employee::factory()->reportingTo($ada)->create(['name' => 'Ben']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);
    $dev = Employee::factory()->reportingTo($cara)->create(['name' => 'Dev']);

    return compact('ada', 'ben', 'cara', 'dev');
}

/**
 * Assert the Approvals count the signed-in Employee sees in the sidebar.
 */
function assertApprovalsWaiting(Employee $employee, int $count): void
{
    actingAs($employee)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.approvalsWaiting', $count));
}

it('counts the Pending Work Entries and Pending Time Off of the Manager\'s Direct Reports, but not of Reports further down', function () {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev] = waitingTeam();
    $eve = Employee::factory()->reportingTo($ben)->create(['name' => 'Eve']);
    WorkEntry::factory()->for(assignmentOf($cara))->count(2)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($eve))->create(['date' => '2026-11-10']);
    TimeOff::factory()->unpaid()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    WorkEntry::factory()->for(assignmentOf($dev))->create(['date' => '2026-11-10']);
    TimeOff::factory()->unpaid()->for($dev)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    assertApprovalsWaiting($ben, 4);
    assertApprovalsWaiting($cara, 2);
});

it('counts nothing for an Employee who manages no one, their own Pending Work Entries and Time Off included', function () {
    ['dev' => $dev] = waitingTeam();
    WorkEntry::factory()->for(assignmentOf($dev))->create(['date' => '2026-11-10']);
    TimeOff::factory()->unpaid()->for($dev)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    assertApprovalsWaiting($dev, 0);
});

it('counts for the next Manager up once the Employee\'s Manager has left', function () {
    ['ada' => $ada, 'ben' => $ben, 'cara' => $cara] = waitingTeam();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    TimeOff::factory()->unpaid()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    $ben->update(['leaving_date' => '2026-11-10']);

    assertApprovalsWaiting($ada, 2);
});

it('counts below several Managers in a row who have left, for the first one up who hasn\'t, and nothing for those who have left', function () {
    ['ada' => $ada, 'ben' => $ben, 'cara' => $cara, 'dev' => $dev] = waitingTeam();
    WorkEntry::factory()->for(assignmentOf($dev))->create(['date' => '2026-11-09']);
    TimeOff::factory()->unpaid()->for($dev)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    $ben->update(['leaving_date' => '2026-11-10']);
    $cara->update(['leaving_date' => '2026-11-10']);

    assertApprovalsWaiting($ada, 2);
    expect($ben->waitingForDecision())->toBe(['workEntries' => 0, 'timeOff' => 0])
        ->and($cara->waitingForDecision())->toBe(['workEntries' => 0, 'timeOff' => 0]);
});

it('keeps counting for a Manager on their Leaving Date', function () {
    ['ben' => $ben, 'cara' => $cara] = waitingTeam();
    TimeOff::factory()->unpaid()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    $ben->update(['leaving_date' => '2026-11-11']);

    assertApprovalsWaiting($ben, 1);
});

it('counts only Pending Work Entries and Pending Time Off', function () {
    ['ben' => $ben, 'cara' => $cara] = waitingTeam();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09']);
    WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-06']);
    TimeOff::factory()->unpaid()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    TimeOff::factory()->unpaid()->for($cara)->approved()->create(['first_date' => '2026-12-15', 'last_date' => '2026-12-15']);
    TimeOff::factory()->unpaid()->for($cara)->declined()->create(['first_date' => '2026-12-16', 'last_date' => '2026-12-16']);
    TimeOff::factory()->unpaid()->for($cara)->cancelled()->create(['first_date' => '2026-12-17', 'last_date' => '2026-12-17']);

    assertApprovalsWaiting($ben, 2);
});

it('reads whom the Employee manages once per page for the sidebar\'s Team, Approvals and count', function () {
    ['ben' => $ben, 'cara' => $cara] = waitingTeam();
    TimeOff::factory()->unpaid()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    $walks = 0;
    DB::listen(function (QueryExecuted $query) use (&$walks): void {
        $walks += str_contains($query->sql, 'WITH RECURSIVE down') ? 1 : 0;
    });

    actingAs($ben)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canSeeTeam', true)
            ->where('auth.canSeeApprovals', true)
            ->where('auth.approvalsWaiting', 1));

    expect($walks)->toBe(1);
});

it('counts nothing for a guest', function () {
    get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.approvalsWaiting', 0));
});
