<?php

use App\Enums\EmploymentStatus;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Enums\Progress;
use App\Enums\TimeOffState;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Models\AccessLogEntry;
use App\Models\Assignment;
use App\Models\Builders\AssignmentBuilder;
use App\Models\Client;
use App\Models\Comment;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PayEntry;
use App\Models\PerformanceEvaluation;
use App\Models\ProgressChange;
use App\Models\Project;
use App\Models\Role;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Policies\PerformanceEvaluationPolicy;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(fn () => Storage::fake(TravelExpense::RECEIPT_DISK));

it('seeds a demo company with at least one of everything to try by hand', function () {
    seed(DemoSeeder::class);

    expect(CompanySettings::query()->count())->toBe(1)
        ->and(Department::query()->count())->toBeGreaterThan(1)
        ->and(Role::query()->pluck('name')->all())->toContain('Admin', 'Manager', 'Employee', 'Senior Manager', 'Payroll')
        ->and(Client::query()->count())->toBeGreaterThan(1)
        ->and(Project::query()->where('is_internal', false)->count())->toBeGreaterThan(1)
        ->and(Assignment::query()->notInternal()->count())->toBeGreaterThan(1)
        ->and(PayEntry::query()->where('effective_from', '>', today())->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeTrue()
        ->and(Employee::query()->get()->map(fn (Employee $employee): EmploymentStatus => $employee->employmentStatus())->unique()->values()->all())
        ->toEqualCanonicalizing([EmploymentStatus::Future, EmploymentStatus::Current, EmploymentStatus::Former]);
});

it('gives Ben Pending Work Entries of the Employees he manages to approve, next to ones he Approved or Declined', function () {
    seed(DemoSeeder::class);
    $ben = Employee::query()->where('email', 'ben@acme.test')->sole();
    $managedByBen = Employee::query()->effectivelyManagedBy($ben)->pluck('id');

    $pending = WorkEntry::query()->where('state', WorkEntryState::Pending)->with('employee')->get();

    expect($pending->pluck('employee.name')->unique()->count())->toBeGreaterThan(1)
        ->and($pending->every(fn (WorkEntry $entry): bool => $entry->date->lte(today())))->toBeTrue()
        ->and($pending->filter(fn (WorkEntry $entry): bool => $managedByBen->contains($entry->employee->id))->count())->toBeGreaterThan(1)
        ->and(WorkEntry::query()->where('state', WorkEntryState::Approved)->where('approved_by', $ben->id)->exists())->toBeTrue()
        ->and(WorkEntry::query()->where('state', WorkEntryState::Declined)->where('declined_by', $ben->id)->whereNotNull('decline_reason')->exists())->toBeTrue();
});

it('gives most Employees Paid Days and one none, and Time Off in every state for their Managers to see', function () {
    seed(DemoSeeder::class);
    $paidDays = Employee::query()->pluck('paid_days');
    $ben = Employee::query()->where('email', 'ben@acme.test')->sole();
    $pendingForBen = TimeOff::query()->where('state', TimeOffState::Pending)
        ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($ben)->select('employees.id'));

    expect($paidDays->filter(fn (int $days): bool => $days > 0)->count())->toBeGreaterThan(intdiv($paidDays->count(), 2))
        ->and($paidDays)->toContain(0)
        ->and(TimeOff::query()->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TimeOffState::cases())
        ->and(TimeOff::query()->where('state', TimeOffState::Declined)->whereNull('decline_reason')->exists())->toBeFalse()
        ->and(TimeOff::query()->where('is_paid', false)->exists())->toBeTrue()
        ->and($pendingForBen->exists())->toBeTrue()
        ->and(Employee::query()->get()->every(fn (Employee $employee): bool => $employee->paidDaysLeftIn(today()->year) >= 0))->toBeTrue()
        ->and($ben->can(Permission::ViewTimeOff->value, $pendingForBen->firstOrFail()->employee))->toBeTrue();
});

it('gives Travel Requests in every state, on Client and Internal Assignments, sharing no day with other standing Travel Requests or Time Off', function () {
    seed(DemoSeeder::class);
    $standing = TravelRequest::query()->standing()->get();

    expect(TravelRequest::query()->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TravelRequestState::cases())
        ->and(TravelRequest::query()->where('state', TravelRequestState::Declined)->whereNull('decline_reason')->exists())->toBeFalse()
        ->and(TravelRequest::query()->whereHas('assignment', fn (AssignmentBuilder $assignments) => $assignments->internal())->exists())->toBeTrue()
        ->and(TravelRequest::query()->whereHas('assignment', fn (AssignmentBuilder $assignments) => $assignments->notInternal())->exists())->toBeTrue()
        ->and($standing->every(fn (TravelRequest $travel): bool => $travel->employee->travelRequests()->standing()->overlapping($travel->first_date, $travel->last_date)->count() === 1
            && ! $travel->employee->timeOff()->standing()->overlapping($travel->first_date, $travel->last_date)->exists()))->toBeTrue();
});

it('gives Ben a year of travel in every state, a dozen trips behind him and some ahead, each within its Assignment\'s dates and with two to five Travel Expenses when it takes them', function () {
    seed(DemoSeeder::class);
    $bensTravel = Employee::query()->where('name', 'Ben Ionescu')->sole()->travelRequests()->with('assignment')->withCount('travelExpenses')->get();

    expect($bensTravel->filter(fn (TravelRequest $travel): bool => $travel->last_date->isPast())->count())->toBeGreaterThanOrEqual(12)
        ->and($bensTravel->filter(fn (TravelRequest $travel): bool => $travel->first_date->isFuture())->count())->toBeGreaterThanOrEqual(4)
        ->and($bensTravel->every(fn (TravelRequest $travel): bool => ! $travel->first_date->isBefore($travel->assignment->start_date)
            && ($travel->assignment->end_date === null || ! $travel->last_date->isAfter($travel->assignment->end_date))))->toBeTrue()
        ->and($bensTravel->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TravelRequestState::cases())
        ->and($bensTravel->filter(fn (TravelRequest $travel): bool => $travel->takesExpenses())
            ->every(fn (TravelRequest $travel): bool => $travel->travel_expenses_count >= 2 && $travel->travel_expenses_count <= 5))->toBeTrue();
});

it('gives Travel Expenses only on Travel Requests that take them, each with a PDF receipt on the receipt disk', function () {
    seed(DemoSeeder::class);
    $expenses = TravelExpense::query()->with('travelRequest')->get();

    expect($expenses->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TravelExpenseState::cases())
        ->and($expenses->every(fn (TravelExpense $expense): bool => $expense->travelRequest->takesExpenses()
            && ! $expense->paid_on->isFuture()
            && str_starts_with((string) Storage::disk(TravelExpense::RECEIPT_DISK)->get($expense->receipt_path), '%PDF-')))->toBeTrue();
});

it('gives some Employees with a Manager an Open Performance Evaluation for this year with two to four Goals, and leaves others to start', function () {
    seed(DemoSeeder::class);

    $evaluations = PerformanceEvaluation::query()->where('year', today()->year)->with('employee')->withCount('goals')->get();

    expect($evaluations->count())->toBeGreaterThanOrEqual(3)
        ->and($evaluations->pluck('state')->unique()->values()->all())->toBe([PerformanceEvaluationState::Open])
        ->and($evaluations->every(fn (PerformanceEvaluation $evaluation): bool => $evaluation->goals_count >= 2 && $evaluation->goals_count <= 4
            && ! $evaluation->employee->hasNoManager()))->toBeTrue()
        ->and(Employee::query()->whereNotNull('manager_id')->whereDoesntHave('performanceEvaluations')->exists())->toBeTrue();
});

it('gives this year\'s Goals a mix of Progress, each with the history that led to it, at least one At Risk', function () {
    seed(DemoSeeder::class);

    $goals = Goal::query()->whereHas('performanceEvaluation', fn ($evaluations) => $evaluations->where('year', today()->year))->with('progressChanges', 'performanceEvaluation')->get();

    expect($goals->pluck('progress')->unique()->sort()->values()->all())->toEqualCanonicalizing([Progress::NotStarted, Progress::OnTrack, Progress::AtRisk, Progress::Done])
        ->and($goals->every(fn (Goal $goal): bool => $goal->progress === ($goal->progressChanges->first()->progress ?? Progress::NotStarted)
            && $goal->progressChanges->every(fn (ProgressChange $change): bool => $goal->created_at !== null && $change->created_at->betweenIncluded($goal->created_at, now())
                && app(PerformanceEvaluationPolicy::class)->changeProgress($change->changedBy, $goal->performanceEvaluation))))->toBeTrue();
});

it('gives a few of this year\'s Goals a thread of Comments from both the Employee and their Manager', function () {
    seed(DemoSeeder::class);

    $commented = Goal::query()->has('comments')->with('comments.author', 'performanceEvaluation.employee')->get();

    expect($commented->count())->toBeGreaterThanOrEqual(2)
        ->and($commented->every(fn (Goal $goal): bool => $goal->performanceEvaluation->year === today()->year
            && $goal->comments->pluck('author_id')->unique()->count() >= 2
            && $goal->comments->contains('author_id', $goal->performanceEvaluation->employee_id)
            && $goal->comments->every(fn (Comment $comment): bool => app(PerformanceEvaluationPolicy::class)->comment($comment->author, $goal->performanceEvaluation)
                && $goal->created_at !== null && $comment->created_at->betweenIncluded($goal->created_at, now()))))->toBeTrue();
});

it('gives some Employees a Completed Performance Evaluation for last year, with an Outcome on every Goal and on the whole', function () {
    seed(DemoSeeder::class);

    $completed = PerformanceEvaluation::query()->where('state', PerformanceEvaluationState::Completed)->with('goals')->get();

    expect($completed->count())->toBeGreaterThanOrEqual(2)
        ->and($completed->every(fn (PerformanceEvaluation $evaluation): bool => $evaluation->year === today()->year - 1
            && $evaluation->outcome !== null && filled($evaluation->summary) && $evaluation->completed_at !== null
            && $evaluation->goals->isNotEmpty() && $evaluation->goals->every(fn (Goal $goal): bool => $goal->outcome !== null)))->toBeTrue();
});

it('clears receipts left over from an earlier demo before adding its own', function () {
    Storage::disk(TravelExpense::RECEIPT_DISK)->put(TravelExpense::RECEIPT_FOLDER.'/left-over.pdf', '%PDF-');

    seed(DemoSeeder::class);

    expect(Storage::disk(TravelExpense::RECEIPT_DISK)->files(TravelExpense::RECEIPT_FOLDER))
        ->toEqualCanonicalizing(TravelExpense::query()->pluck('receipt_path')->all());
});

it('lets the demo Admin sign in with the demo password', function () {
    seed(DemoSeeder::class);

    post(route('login.store'), ['email' => 'ana@acme.test', 'password' => 'password'])
        ->assertRedirect(route('landing', absolute: false));
});

it('leaves an installed company alone', function () {
    Notification::fake();
    seed(DemoSeeder::class);

    seed(DemoSeeder::class);

    expect(CompanySettings::query()->count())->toBe(1)
        ->and(Employee::query()->where('email', 'ana@acme.test')->count())->toBe(1);
});
