<?php

namespace Database\Seeders;

use App\Actions\AddEmployee;
use App\Actions\AssessPerformanceEvaluation;
use App\Actions\ChangeAccess;
use App\Actions\ChangeGoalProgress;
use App\Actions\InstallCompany;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\Outcome;
use App\Enums\PayKind;
use App\Enums\Permission;
use App\Enums\Progress;
use App\Enums\Scope;
use App\Enums\TimeOffState;
use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small demo company with at least one of everything, so every flow can be
 * tried by hand. It installs the company the way hr:install does, then adds
 * Employees, Roles, Clients, Projects, Assignments and Pay through the same
 * actions the app uses, then fills the year so far, from 1 January to
 * today: Work Entries on every working day for everyone with a Manager,
 * Paid Days (none for Ivan), Time Off through the year, Travel Requests
 * for most Employees, all in every state, a year of Ben's travel among
 * them, Travel Expenses with small generated PDF
 * receipts, replacing any an earlier demo left behind, and this year's
 * Open Performance Evaluations with Goals for Ben, Cara, Lia and Ivan,
 * their Progress changed over the last two months, a few of them At Risk,
 * and short threads of Comments from both sides on a few,
 * Completed ones for last year for Ben, Cara and Ivan,
 * leaving Hana's and Finn's for Ana to start. Dates are relative
 * to today, so the Future and Former Employees stay that way, and the last two weeks of Work Entries and some of the Time Off
 * still to come are always Pending, waiting for each Employee's Manager (Ana, Ben
 * or Hana) to decide them. Everyone except the Future Employee can sign in
 * with the password "password"; the Future Employee still has an invitation
 * waiting. Invitations are not emailed, so the seeder prints a fresh link to it.
 *
 *   Ana (no Manager, Admin)
 *   ├── Ben (Manager)
 *   │   ├── Cara (Employee; Client work and a planned raise)
 *   │   ├── Dan (Future Employee, invitation pending)
 *   │   └── Eve (Former Employee)
 *   │       └── Lia (her Manager has left, so Ben is her effective Manager)
 *   ├── Hana (Manager)
 *   │   └── Ivan (leaving in six months)
 *   └── Finn (Payroll: everyone's Pay; Auditor)
 */
class DemoSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    /**
     * Days nobody records work on, as month and day.
     *
     * @var list<string>
     */
    private const array PUBLIC_HOLIDAYS = ['01-01', '01-02', '05-01', '08-15', '11-30', '12-01', '12-25', '12-26'];

    /**
     * How many minutes a day's work strays from the usual, taken in turn.
     *
     * @var list<int>
     */
    private const array DAILY_VARIATION = [0, 30, -30, 0, 60, 15, -60, 30, 0, -15, 45, -30];

    /** Why a Manager declines a client dinner on a Travel Expense. */
    private const string CLIENT_DINNER_DECLINED = 'Client dinners go through the company card. Could you add only your own meal?';

    public function __construct(
        private InstallCompany $installCompany,
        private AddEmployee $addEmployee,
        private ChangeAccess $changeAccess,
        private ChangeGoalProgress $changeGoalProgress,
        private UpdateTrimmingAssignments $updateTrimmingAssignments,
        private AssessPerformanceEvaluation $assessPerformanceEvaluation,
    ) {}

    /**
     * Seed the demo company, unless a company is already installed.
     */
    public function run(): void
    {
        if ($this->installCompany->isInstalled()) {
            $this->command?->warn('A company is already installed, so the demo data was not added. Run `php artisan migrate:fresh --seed` to start over.');

            return;
        }

        config(['mail.default' => 'array']);
        Storage::disk(TravelExpense::RECEIPT_DISK)->deleteDirectory(TravelExpense::RECEIPT_FOLDER);
        $today = today();

        $ana = $this->installCompany->install('Acme Software', 'EUR', 'Engineering', 'Ana Popescu', 'ana@acme.test');
        $this->updateTrimmingAssignments->updateEmployee($ana, ['start_date' => $today->subYears(4)]);
        $ana->internalAssignment()->sole()->update(['job_title' => 'CTO']);
        $this->letSignIn($ana);

        $engineering = Department::query()->where('name', 'Engineering')->sole();
        $design = Department::query()->create(['name' => 'Design']);
        $finance = Department::query()->create(['name' => 'Finance']);
        Department::query()->create(['name' => 'Legal']);

        $payroll = $this->changeAccess->createRole($ana, 'Payroll', [
            Permission::ViewPay->value => Scope::Everyone->value,
            Permission::ManagePay->value => Scope::Everyone->value,
        ], givenToNewEmployees: false);
        $auditor = $this->changeAccess->createRole($ana, 'Auditor', [
            Permission::ViewDirectory->value => null,
            Permission::ViewProjects->value => null,
        ], givenToNewEmployees: false);

        $ben = $this->add($ana, 'Ben Ionescu', 'Engineering Manager', $engineering, $ana, $today->subYears(3));
        $hana = $this->add($ana, 'Hana Pop', 'Design Lead', $design, $ana, $today->subYears(2));
        $finn = $this->add($ana, 'Finn Muller', 'Payroll Specialist', $finance, $ana, $today->subYears(2));
        $cara = $this->add($ana, 'Cara Lane', 'Senior Developer', $engineering, $ben, $today->subMonths(18));
        $eve = $this->add($ana, 'Eve Stan', 'QA Engineer', $engineering, $ben, $today->subYears(2));
        $lia = $this->add($ana, 'Lia Rusu', 'Developer', $engineering, $eve, $today->subYear());
        $ivan = $this->add($ana, 'Ivan Petrov', 'Product Designer', $design, $hana, $today->subYear(), $today->addMonths(6));
        $dan = $this->add($ana, 'Dan Moraru', 'Developer', $engineering, $ben, $today->addMonth(), canSignIn: false);
        $this->updateTrimmingAssignments->updateEmployee($eve, ['leaving_date' => $today->subMonths(2)]);

        $manager = Role::query()->where('name', 'Manager')->sole();
        $this->changeAccess->giveRole($ana, $manager, $ben);
        $this->changeAccess->giveRole($ana, $manager, $hana);
        $this->changeAccess->giveRole($ana, $payroll, $finn);
        $this->changeAccess->giveRole($ana, $auditor, $finn);

        $globex = Client::query()->create(['name' => 'Globex']);
        $initech = Client::query()->create(['name' => 'Initech']);
        Client::query()->create(['name' => 'Umbrella']);

        $relaunch = $this->project('Relaunch', $globex, $today->subYear());
        $audit = $this->project('Data Audit', $globex, $today->subMonths(14), $today->subMonths(2));
        $mobileApp = $this->project('Mobile App', $initech, $today->addMonths(2));
        $website = $this->project('Website Revamp', null, $today->subMonths(6), $today->addMonths(4));
        $this->project('Archive Cleanup', null, $today->subMonth());

        $this->internal($ana, 100, [['fixed', 900000, null]]);
        $this->internal($ben, 80, [['fixed', 650000, null]]);
        $this->internal($hana, 100, [['fixed', 580000, null]]);
        $this->internal($finn, 100, [['fixed', 450000, null]]);
        $this->internal($cara, 0, [
            ['fixed', 420000, null],
            ['fixed', 480000, $today->subMonths(3)],
            ['fixed', 520000, $today->addMonths(2)],
        ]);
        $this->internal($eve, 0, [['fixed', 400000, null]]);
        $this->internal($lia, 20, [['fixed', 350000, null]]);
        $this->internal($ivan, 50, [['fixed', 390000, null]]);
        $this->internal($dan, 0, []);

        $caraOnRelaunch = $this->assign($cara, $relaunch, 'Tech Lead', 60, $today->subYear(), null, [['hourly', 2500, null]]);
        $caraOnAudit = $this->assign($cara, $audit, 'Developer', 40, $today->subMonths(14), $today->subMonths(2), [['hourly', 2200, null]]);
        $benOnRelaunch = $this->assign($ben, $relaunch, 'Delivery Lead', 20, $today->subYear(), null, []);
        $eveOnAudit = $this->assign($eve, $audit, 'QA Engineer', 100, $today->subMonths(14), $today->subMonths(2), [['hourly', 2000, null]]);
        $liaOnRelaunch = $this->assign($lia, $relaunch, 'Developer', 80, $today->subMonths(6), null, [['hourly', 1800, null]]);
        $ivanOnWebsite = $this->assign($ivan, $website, 'Designer', 50, $today->subMonths(6), $today->addMonths(4), [['hourly', 3000, null]]);
        $this->assign($dan, $mobileApp, 'Developer', 100, $today->addMonths(2), null, []);

        foreach ([$ana, $ben, $hana, $finn, $cara, $eve, $lia, $dan] as $employee) {
            $this->paidDays($employee, $employee->manager_id === null ? 25 : 20);
        }
        $this->paidDays($ivan, 0);

        $thisMonday = $today->startOfWeek(CarbonInterface::MONDAY);
        $this->timeOff($ana, $thisMonday->subWeeks(6), 4, true, TimeOffState::Approved);
        $this->timeOff($ana, $thisMonday->addWeeks(3), 4, true, TimeOffState::Approved);
        $this->timeOff($ben, $thisMonday->addWeeks(4), 4, true, TimeOffState::Approved, $ana);
        $this->timeOff($cara, $thisMonday->subWeeks(5), 2, true, TimeOffState::Approved, $ben);
        $this->timeOff($cara, $thisMonday->addWeeks(2), 4, true);
        $this->timeOff($cara, $thisMonday->addWeeks(5)->addDays(3), 1, true, TimeOffState::Cancelled);
        $this->timeOff($lia, $thisMonday->addWeeks(3), 1, false);
        $this->timeOff($ivan, $thisMonday->addWeeks(2), 2, false, TimeOffState::Declined, $hana, 'Launch week for the Website Revamp. Could you take the week after?');
        $this->timeOff($ivan, $thisMonday->addWeeks(3)->addDays(4), 0, false);
        $this->yearOfTimeOff($thisMonday, $ana, $ben, $hana, $finn, $cara, $eve, $lia, $ivan);

        $this->yearOfWork($ana, $ben, $hana, $finn, $eve, $lia, $ivan, $caraOnRelaunch, $caraOnAudit, $benOnRelaunch, $eveOnAudit, $liaOnRelaunch, $ivanOnWebsite);
        $this->decline($caraOnRelaunch, $thisMonday->subWeek(), $ben, 'Sprint planning goes on Internal, not Relaunch');
        $this->decline($ivanOnWebsite, $thisMonday->subWeek()->addDays(2), $hana, 'This looks like a copy of Monday. Could you check the hours?');

        $workshop = $this->travelRequest($caraOnRelaunch, 'Berlin', $thisMonday->subWeeks(3)->addDay(), 2, 'Checkout API workshop with Globex', TravelRequestState::Approved, $ben);
        $this->travelRequest($caraOnRelaunch, 'Berlin', $thisMonday->addWeeks(3)->addDay(), 2, 'Relaunch go-live with Globex');
        $this->travelRequest($caraOnRelaunch, 'Munich', $thisMonday->addWeeks(1)->addDays(2), 1, 'Relaunch demo, moved online', TravelRequestState::Cancelled);
        $this->travelRequest($liaOnRelaunch, 'Berlin', $thisMonday->addWeeks(1)->addDays(3), 3, 'Pairing on the cart with the Globex team');
        $this->travelRequest($ivanOnWebsite, 'Lisbon', $thisMonday->addWeek(), 1, 'Design conference', TravelRequestState::Declined, $hana, 'Launch prep that week. Could you watch the talks online?');
        $investors = $this->travelRequest($ana->internalAssignment()->sole(), 'London', $thisMonday->addWeeks(6), 4, 'Meeting investors', TravelRequestState::Approved);

        $this->travelExpense($workshop, $workshop->first_date, TravelExpenseCategory::Transport, 18900, 'Train to Berlin and back', TravelExpenseState::Approved, $ben);
        $this->travelExpense($workshop, $workshop->last_date, TravelExpenseCategory::Accommodation, 39800, 'Hotel, two nights');
        $this->travelExpense($workshop, $workshop->first_date->addDay(), TravelExpenseCategory::Meals, 14250, 'Team dinner with Globex', TravelExpenseState::Declined, $ben, self::CLIENT_DINNER_DECLINED);
        $this->travelExpense($investors, $today->subDays(3), TravelExpenseCategory::Transport, 32000, 'Flights to London, booked early', TravelExpenseState::Approved);
        $this->bensTravel($benOnRelaunch, $ben->internalAssignment()->sole(), $ana, $thisMonday, $today);
        $this->yearOfTravel($thisMonday, $ana, $ben, $hana, $finn, $eve, $lia, $ivan, $caraOnRelaunch, $caraOnAudit, $eveOnAudit, $liaOnRelaunch, $ivanOnWebsite);

        $this->completedPerformanceEvaluation($ben, [
            ['Take over the Engineering team', 'Run planning and one-to-ones for everyone on the team.', Outcome::Exceeded],
            ['Start the Relaunch', 'Agree the Relaunch scope with Globex and staff it.', Outcome::Met],
        ], Outcome::Exceeded, 'Ben took the team over smoothly and had the Relaunch staffed a month early.');
        $this->completedPerformanceEvaluation($cara, [
            ['Finish the Data Audit', 'Deliver the Globex Data Audit report.', Outcome::Met],
            ['Learn the payments stack', 'Ship one payments change end to end.', Outcome::PartlyMet],
        ], Outcome::Met, 'Cara delivered the Data Audit and is close to owning payments on her own.');
        $this->completedPerformanceEvaluation($ivan, [
            ['Design the new homepage', 'Get the homepage mock-ups signed off.', Outcome::Met],
        ], Outcome::Met, 'Ivan settled into the team quickly and the homepage was signed off first time.');

        $this->performanceEvaluation($ben, [
            ['Grow two Tech Leads', 'Cara and Lia each run a Relaunch stream on their own by the end of the year.', [[Progress::OnTrack, $ben]]],
            ['Ship the Relaunch', 'Go live with Globex on the date agreed, with no rollback.', [[Progress::OnTrack, $ben], [Progress::AtRisk, $ana]], [
                [$ana, 'Globex moved the go-live a week earlier. Can we still make it?'],
                [$ben, 'Only if the cart work lands this sprint. I\'ve marked it At Risk until then.'],
            ]],
            ['Hire a QA Engineer', 'Fill the role Eve left, with the new hire onboarded.', [[Progress::OnTrack, $ben], [Progress::Done, $ben]]],
            ['Cut review time', 'Pull requests wait under a day for their first review.', []],
        ]);
        $this->performanceEvaluation($cara, [
            ['Lead the Checkout API', 'Own its design and delivery, and present it at the Globex workshop.', [[Progress::OnTrack, $cara], [Progress::AtRisk, $ben]], [
                [$cara, 'The API design is signed off by Globex.'],
                [$ben, 'Nice. Auth is late though, let\'s talk Friday.'],
                [$cara, 'Friday works. I\'ll bring a plan B for the token service.'],
            ]],
            ['Mentor Lia', 'Pair with Lia weekly until she reviews pull requests on her own.', [[Progress::OnTrack, $cara]]],
            ['Cut payment errors', 'Bring failed payments below one in a thousand.', []],
        ]);
        $this->performanceEvaluation($lia, [
            ['Own the cart', 'Take the cart from design to release on the Relaunch.', [[Progress::OnTrack, $lia]], [
                [$ben, 'How is the cart going?'],
                [$lia, 'Designs are done, building the checkout step next.'],
            ]],
            ['Review code', 'Review at least two pull requests a week.', [[Progress::OnTrack, $ben], [Progress::Done, $lia]]],
        ]);
        $this->performanceEvaluation($ivan, [
            ['Launch the Website Revamp', 'Hand over the final designs before the launch date.', [[Progress::OnTrack, $ivan], [Progress::AtRisk, $hana], [Progress::OnTrack, $ivan]]],
            ['Build the icon set', 'Finish one icon set the whole Website Revamp uses.', []],
        ]);

        $this->command?->info('Demo company seeded. Sign in as ana@acme.test (Admin), ben@acme.test, hana@acme.test, finn@acme.test, cara@acme.test, ivan@acme.test or lia@acme.test with the password "password".');
        $this->command?->info('Dan\'s invitation: '.$this->invitationLink($dan));
    }

    /**
     * A fresh link to the Employee's invitation, since the token created
     * when they were added is stored hashed. It replaces that one.
     */
    private function invitationLink(Employee $employee): string
    {
        return route('invitation.show', [
            'token' => Password::broker('invites')->createToken($employee),
            'email' => $employee->email,
        ]);
    }

    /**
     * Add an Employee the way the app does, then, unless they are still to
     * accept their invitation, let them sign in with the demo password.
     */
    private function add(Employee $actor, string $name, string $jobTitle, Department $department, Employee $manager, CarbonInterface $startDate, ?CarbonInterface $leavingDate = null, bool $canSignIn = true): Employee
    {
        $employee = $this->addEmployee->add($actor, [
            'name' => $name,
            'email' => Str::lower(Str::before($name, ' ')).'@acme.test',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
            'start_date' => $startDate,
            'leaving_date' => $leavingDate,
            'job_title' => $jobTitle,
        ]);

        if ($canSignIn) {
            $this->letSignIn($employee);
        }

        return $employee;
    }

    /**
     * Act as if the Employee accepted their invitation and chose the demo
     * password.
     */
    private function letSignIn(Employee $employee): void
    {
        $employee->forceFill(['password' => self::PASSWORD, 'email_verified_at' => now()])->save();
    }

    private function project(string $name, ?Client $client, CarbonInterface $startDate, ?CarbonInterface $endDate = null): Project
    {
        return Project::query()->create([
            'name' => $name,
            'client_id' => $client?->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Set the Employee's internal Capacity, and give their Internal
     * Assignment its Pay history.
     *
     * @param  list<array{0: string, 1: int, 2: CarbonInterface|null}>  $pay  Kind, amount in cents, and the day it takes effect (null for the first day)
     */
    private function internal(Employee $employee, int $capacity, array $pay): void
    {
        $internal = $employee->internalAssignment()->sole();
        $internal->update(['capacity' => $capacity]);

        $this->pay($internal, $pay);
    }

    /**
     * Put the Employee on a Project, with a Pay history.
     *
     * @param  list<array{0: string, 1: int, 2: CarbonInterface|null}>  $pay  Kind, amount in cents, and the day it takes effect (null for the first day)
     */
    private function assign(Employee $employee, Project $project, string $jobTitle, int $capacity, CarbonInterface $startDate, ?CarbonInterface $endDate, array $pay): Assignment
    {
        $assignment = $employee->assignments()->create([
            'project_id' => $project->id,
            'job_title' => $jobTitle,
            'capacity' => $capacity,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $this->pay($assignment, $pay);

        return $assignment;
    }

    /**
     * The year's Work Entries for everyone who records them, which is
     * everyone with a Manager who has started: hours on each of their
     * Assignments by its Capacity, what they did changing as the year
     * goes on. Ana has no Manager, so she records none, and Ben records
     * only his Relaunch hours.
     */
    private function yearOfWork(Employee $ana, Employee $ben, Employee $hana, Employee $finn, Employee $eve, Employee $lia, Employee $ivan, Assignment $caraOnRelaunch, Assignment $caraOnAudit, Assignment $benOnRelaunch, Assignment $eveOnAudit, Assignment $liaOnRelaunch, Assignment $ivanOnWebsite): void
    {
        $auditEnded = $caraOnAudit->end_date?->toImmutable() ?? today();
        $relaunchJoined = $liaOnRelaunch->start_date->toImmutable();
        $websiteStarted = $ivanOnWebsite->start_date->toImmutable();
        $eveLeft = $eve->leaving_date?->toImmutable() ?? today();
        $liasManager = fn (CarbonInterface $approvedAt): Employee => $approvedAt->isAfter($eveLeft) ? $ben : $eve;
        $liaInternal = $lia->internalAssignment()->sole();
        $ivanInternal = $ivan->internalAssignment()->sole();

        $this->recordWork($caraOnRelaunch, 300, [
            ['Checkout API design', 'Payment provider research', 'Sprint planning', 'Reviewing pull requests'],
            ['Checkout API endpoints', 'Token service spike', 'Reviewing pull requests', 'Sprint planning'],
            ['Checkout API load tests', 'Fixing payment bugs', 'Pairing with Lia', 'Reviewing pull requests'],
        ], $ben, until: $auditEnded);
        $this->recordWork($caraOnRelaunch, 420, [
            ['Checkout API', 'Fixing payment bugs', 'Reviewing pull requests', 'Sprint planning'],
            ['Go-live checklist', 'Checkout API', 'Reviewing pull requests', 'Fixing payment bugs'],
        ], $ben, from: $auditEnded->addDay());
        $this->recordWork($caraOnAudit, 180, [
            ['Data Audit interviews', 'Mapping Globex data sources'],
            ['Writing audit queries', 'Data quality checks'],
            ['Data Audit report', 'Findings review with Globex'],
        ], $ben);
        $this->recordWork($benOnRelaunch, 120, [
            ['Relaunch planning', 'Globex status call', 'Staffing the Relaunch'],
            ['Roadmap review', 'Globex status call', 'Relaunch risks review'],
            ['Reviewing the release plan', 'Globex status call', 'Go-live planning'],
        ], $ana);
        $this->recordWork($eveOnAudit, 450, [
            ['Data Audit test plan', 'Test data setup'],
            ['Regression testing', 'Data quality checks'],
            ['Final audit checks', 'Handover before leaving'],
        ], $ben);
        $this->recordWork($liaInternal, 420, [
            ['Internal tools: fixing the build', 'Code review practice'],
            ['Internal tools: timesheet export', 'Learning the payments stack'],
        ], $liasManager, until: $relaunchJoined->subDay());
        $this->recordWork($liaInternal, 90, [['Team meeting', 'Internal tools upkeep']], $liasManager, from: $relaunchJoined);
        $this->recordWork($liaOnRelaunch, 360, [
            ['Product listing page', 'Pairing with Cara'],
            ['Unit tests for the cart', 'Cart design review', 'Product listing page'],
            ['Cart checkout step', 'Pairing with Cara', 'Unit tests for the cart'],
        ], $liasManager);
        $this->recordWork($ivanInternal, 450, [
            ['Brand guidelines', 'Design review with Hana'],
            ['Design system components', 'Icon research'],
        ], $hana, until: $websiteStarted->subDay());
        $this->recordWork($ivanInternal, 210, [['Design system components', 'Design review with Hana', 'Team meeting']], $hana, from: $websiteStarted);
        $this->recordWork($ivanOnWebsite, 240, [
            ['Website research and sitemap', 'Wireframes'],
            ['Homepage mock-ups', 'Design review with Hana'],
            ['Icon set', 'Page templates', 'Handover notes for developers'],
        ], $hana);
        $this->recordWork($hana->internalAssignment()->sole(), 450, [
            ['Hiring a product designer', 'Design team planning', 'Design review'],
            ['Design system roadmap', 'One-to-ones', 'Design review'],
            ['Website Revamp oversight', 'One-to-ones', 'Design review'],
        ], $ana);
        $this->recordWork($finn->internalAssignment()->sole(), 450, [
            ['Year-end payroll close', 'Tax forms for last year'],
            ['Monthly payroll run', 'Pay reviews'],
            ['Monthly payroll run', 'Travel Expense reimbursements', 'Pay change paperwork'],
        ], $ana);
    }

    /**
     * Record Work Entries on the Assignment for every working day from the
     * start of the year, or four weeks back if that is earlier, or from the
     * given day, to today, or the given day,
     * within the Assignment's and the Employee's dates. Working days are
     * weekdays that aren't public holidays or the Employee's Pending or
     * Approved Time Off. Each day takes around the given minutes, give or
     * take an hour, and one of the descriptions of its phase: the
     * phases split the days evenly, in order. This week and last are
     * Pending; earlier weeks were Approved the following Monday, by the
     * approver, or by whoever the closure names for that time.
     *
     * @param  list<list<string>>  $phases
     * @param  Employee|Closure(CarbonInterface): Employee  $approver
     */
    private function recordWork(Assignment $assignment, int $minutes, array $phases, Employee|Closure $approver, ?CarbonInterface $from = null, ?CarbonInterface $until = null): void
    {
        $employee = $assignment->employee;
        $first = collect([today()->startOfYear()->min(today()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(3)), $assignment->start_date->toImmutable(), $employee->start_date->toImmutable(), $from])->filter()->max();
        $last = collect([today(), $assignment->end_date?->toImmutable(), $employee->leaving_date?->toImmutable(), $until])->filter()->min();
        $daysOff = $employee->timeOff()->standing()->get()
            ->flatMap(fn (TimeOff $timeOff): array => array_map(fn (CarbonInterface $day): string => $day->toDateString(), CarbonPeriod::create($timeOff->first_date, $timeOff->last_date)->toArray()))
            ->flip();
        $days = collect(CarbonPeriod::create($first, $last)->toArray())
            ->map(fn (CarbonInterface $day): CarbonImmutable => CarbonImmutable::parse($day))
            ->reject(fn (CarbonImmutable $day): bool => $day->isWeekend() || in_array($day->format('m-d'), self::PUBLIC_HOLIDAYS, true) || $daysOff->has($day->toDateString()))
            ->values();
        $pendingFrom = today()->startOfWeek(CarbonInterface::MONDAY)->subWeek();
        $rows = [];

        foreach ($days as $position => $day) {
            $descriptions = $phases[intdiv($position * count($phases), $days->count())];
            $approvedAt = $day->startOfWeek(CarbonInterface::MONDAY)->addWeek()->setTime(10, 0);
            $isApproved = $day->isBefore($pendingFrom);
            $approvedBy = $approver instanceof Closure ? $approver($approvedAt) : $approver;

            $rows[] = [
                'assignment_id' => $assignment->id,
                'date' => $day->toDateString(),
                'minutes' => max(60, $minutes + self::DAILY_VARIATION[($day->dayOfYear + $assignment->id) % count(self::DAILY_VARIATION)]),
                'description' => $descriptions[($day->weekOfYear + $day->dayOfWeek) % count($descriptions)],
                'state' => ($isApproved ? WorkEntryState::Approved : WorkEntryState::Pending)->value,
                'approved_by' => $isApproved ? $approvedBy->id : null,
                'approved_at' => $isApproved ? $approvedAt : null,
                'created_at' => $day->setTime(17, 30)->min(now()),
                'updated_at' => $isApproved ? $approvedAt : $day->setTime(17, 30)->min(now()),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            WorkEntry::query()->insert($chunk);
        }
    }

    /**
     * Time Off through the year so far, past the last few weeks: a week
     * or two in summer for most, a long weekend here and there, some
     * Unpaid days, one Declined and one Cancelled. Anything that would
     * start before this year is left out. With the Time Off still to come,
     * nobody uses more Paid Days than they have.
     */
    private function yearOfTimeOff(CarbonInterface $thisMonday, Employee $ana, Employee $ben, Employee $hana, Employee $finn, Employee $cara, Employee $eve, Employee $lia, Employee $ivan): void
    {
        $approved = TimeOffState::Approved;

        /**
         * Each: Employee, weeks before this Monday, weekday (0 for
         * Monday), days after it, Paid, state, decider, decline reason.
         *
         * @var list<array{Employee, int, int, int, bool, TimeOffState, Employee|null, string|null}> $timeOff
         */
        $timeOff = [
            [$ana, 45, 0, 2, true, $approved, null, null],
            [$ana, 15, 0, 11, true, $approved, null, null],
            [$ben, 33, 4, 0, true, $approved, $ana, null],
            [$ben, 19, 0, 11, true, $approved, $ana, null],
            [$hana, 36, 0, 4, true, $approved, $ana, null],
            [$hana, 13, 0, 11, true, $approved, $ana, null],
            [$hana, 4, 4, 0, true, $approved, $ana, null],
            [$finn, 40, 0, 4, true, $approved, $ana, null],
            [$finn, 26, 0, 1, false, $approved, $ana, null],
            [$finn, 17, 0, 11, true, $approved, $ana, null],
            [$finn, 8, 2, 0, true, TimeOffState::Cancelled, null, null],
            [$cara, 30, 0, 4, true, $approved, $ben, null],
            [$cara, 22, 0, 1, true, TimeOffState::Declined, $ben, 'The Relaunch beta goes out that week. Could you move it to July?'],
            [$cara, 14, 0, 4, true, $approved, $ben, null],
            [$eve, 28, 0, 4, true, $approved, $ben, null],
            [$eve, 17, 0, 4, true, $approved, $ben, null],
            [$lia, 24, 0, 4, true, $approved, $eve, null],
            [$lia, 10, 0, 4, true, $approved, $eve, null],
            [$lia, 1, 4, 0, true, $approved, $ben, null],
            [$ivan, 20, 0, 4, false, $approved, $hana, null],
            [$ivan, 6, 4, 0, false, $approved, $hana, null],
        ];

        foreach ($timeOff as [$employee, $weeksAgo, $weekday, $daysAfter, $isPaid, $state, $decider, $declineReason]) {
            $first = $thisMonday->subWeeks($weeksAgo)->addDays($weekday);

            if ($first->isBefore(today()->startOfYear())) {
                continue;
            }

            $this->timeOff($employee, $first, $daysAfter, $isPaid, $state, $decider, $declineReason);
        }
    }

    /**
     * Travel through the year so far for everyone besides Ben, whose
     * year of travel has its own method: Client visits on Relaunch, the
     * Data Audit and the Website Revamp, and conferences, courses and a
     * board meeting on Internal Assignments, with their Travel Expenses,
     * almost all of them Approved by now. Anything that would start
     * before this year is left out. None shares a day with the
     * traveller's Time Off.
     */
    private function yearOfTravel(CarbonInterface $thisMonday, Employee $ana, Employee $ben, Employee $hana, Employee $finn, Employee $eve, Employee $lia, Employee $ivan, Assignment $caraOnRelaunch, Assignment $caraOnAudit, Assignment $eveOnAudit, Assignment $liaOnRelaunch, Assignment $ivanOnWebsite): void
    {
        $approvedTrip = TravelRequestState::Approved;
        [$transport, $accommodation, $meals, $other] = [TravelExpenseCategory::Transport, TravelExpenseCategory::Accommodation, TravelExpenseCategory::Meals, TravelExpenseCategory::Other];
        [$pendingExpense, $approvedExpense, $declinedExpense] = [TravelExpenseState::Pending, TravelExpenseState::Approved, TravelExpenseState::Declined];
        $ivanInternal = $ivan->internalAssignment()->sole();

        /**
         * Each trip: Assignment, weeks before this Monday, weekday (0 for
         * Monday), days after it, destination, purpose, state, who decided
         * it, who decided its Travel Expenses, and the Travel Expenses as
         * [day of the trip it was paid, category, amount in cents,
         * description, state, decline reason].
         *
         * @var list<array{Assignment, int, int, int, string, string, TravelRequestState, Employee|null, Employee|null, list<array{int, TravelExpenseCategory, int, string, TravelExpenseState, string|null}>}> $trips
         */
        $trips = [
            [$ana->internalAssignment()->sole(), 34, 0, 2, 'Paris', 'Board meeting', $approvedTrip, null, null, [
                [0, $transport, 31000, 'Flights to Paris and back', $approvedExpense, null],
                [2, $accommodation, 52000, 'Hotel, two nights', $approvedExpense, null],
            ]],
            [$caraOnAudit, 38, 1, 1, 'Frankfurt', 'Data Audit interviews with Globex finance', $approvedTrip, $ben, $ben, [
                [0, $transport, 17400, 'Train to Frankfurt and back', $approvedExpense, null],
                [1, $accommodation, 13900, 'Hotel, one night', $approvedExpense, null],
                [0, $meals, 3800, 'Dinner on the road', $approvedExpense, null],
            ]],
            [$lia->internalAssignment()->sole(), 35, 3, 1, 'Cluj', 'Speaking at a JavaScript meetup', $approvedTrip, $eve, $eve, [
                [0, $transport, 6200, 'Train to Cluj and back', $approvedExpense, null],
                [1, $accommodation, 8500, 'Hotel, one night', $approvedExpense, null],
            ]],
            [$ivanInternal, 32, 2, 2, 'Amsterdam', 'Design systems conference', $approvedTrip, $hana, $hana, [
                [0, $other, 45000, 'Conference ticket', $approvedExpense, null],
                [0, $transport, 28000, 'Flights to Amsterdam and back', $approvedExpense, null],
                [2, $accommodation, 39000, 'Hotel, two nights', $approvedExpense, null],
            ]],
            [$finn->internalAssignment()->sole(), 29, 1, 1, 'Vienna', 'Payroll compliance course', $approvedTrip, $ana, $ana, [
                [0, $other, 52000, 'Course fee', $approvedExpense, null],
                [0, $transport, 24000, 'Flights to Vienna and back', $approvedExpense, null],
                [1, $accommodation, 12000, 'Hotel, one night', $approvedExpense, null],
            ]],
            [$caraOnRelaunch, 27, 2, 2, 'Berlin', 'Checkout API kickoff with Globex', $approvedTrip, $ben, $ben, [
                [0, $transport, 19400, 'Train to Berlin and back', $approvedExpense, null],
                [2, $accommodation, 37000, 'Hotel, two nights', $approvedExpense, null],
                [1, $meals, 4600, 'Lunch with the Globex team', $approvedExpense, null],
            ]],
            [$eveOnAudit, 25, 1, 1, 'Frankfurt', 'Data Audit test plan review', $approvedTrip, $ben, $ben, [
                [0, $transport, 16800, 'Train to Frankfurt and back', $approvedExpense, null],
                [1, $accommodation, 14500, 'Hotel, one night', $approvedExpense, null],
            ]],
            [$hana->internalAssignment()->sole(), 21, 1, 2, 'Berlin', 'Design leadership summit', $approvedTrip, $ana, $ana, [
                [0, $other, 65000, 'Summit ticket', $approvedExpense, null],
                [0, $transport, 18000, 'Flights to Berlin and back', $approvedExpense, null],
                [2, $accommodation, 42000, 'Hotel, two nights', $approvedExpense, null],
            ]],
            [$caraOnAudit, 12, 1, 1, 'Frankfurt', 'Data Audit findings presentation', $approvedTrip, $ben, $ben, [
                [0, $transport, 16800, 'Train to Frankfurt and back', $approvedExpense, null],
                [1, $accommodation, 14500, 'Hotel, one night', $approvedExpense, null],
                [1, $other, 2400, 'Taxi from the station', $pendingExpense, null],
            ]],
            [$ivanOnWebsite, 11, 2, 1, 'Vienna', 'Website user testing sessions', $approvedTrip, $hana, $hana, [
                [0, $transport, 22000, 'Flights to Vienna and back', $approvedExpense, null],
                [1, $accommodation, 16000, 'Hotel, one night', $approvedExpense, null],
                [1, $meals, 4500, 'Lunch for the test participants', $declinedExpense, 'The participants\' lunch went on the company card. Could you remove it?'],
            ]],
            [$liaOnRelaunch, 11, 1, 2, 'Berlin', 'Cart pairing with the Globex team', $approvedTrip, $eve, $eve, [
                [0, $transport, 19900, 'Train to Berlin and back', $approvedExpense, null],
                [2, $accommodation, 35000, 'Hotel, two nights', $approvedExpense, null],
            ]],
            [$ivanOnWebsite, 4, 2, 1, 'Vienna', 'Second round of user testing, moved online', TravelRequestState::Cancelled, $hana, null, []],
        ];

        foreach ($trips as [$assignment, $weeksAgo, $weekday, $daysAfter, $destination, $purpose, $state, $decider, $expenseDecider, $expenses]) {
            $first = $thisMonday->subWeeks($weeksAgo)->addDays($weekday);

            if ($first->isBefore(today()->startOfYear())) {
                continue;
            }

            $travelRequest = $this->travelRequest($assignment, $destination, $first, $daysAfter, $purpose, $state, $decider);

            foreach ($expenses as [$paidOn, $category, $amount, $description, $expenseState, $declineReason]) {
                $this->travelExpense($travelRequest, $first->addDays($paidOn), $category, $amount, $description, $expenseState, $expenseDecider, $declineReason);
            }
        }
    }

    /**
     * When Time Off or a Travel Request starting on the day was asked for:
     * two weeks before, on a weekday, or two days ago if that is earlier.
     */
    private function requestedAt(CarbonInterface $first): CarbonInterface
    {
        return $this->notAfter($first->subDays(14)->setTime(9, 0), daysAgo: 2);
    }

    /**
     * When Time Off or a Travel Request starting on the day was decided:
     * the working day after it was asked for, or as it was asked for when
     * nobody decided it, since an Employee with no Manager has theirs
     * Approved straight away.
     */
    private function decidedAt(CarbonInterface $first, ?Employee $decider): CarbonInterface
    {
        return $decider === null ? $this->requestedAt($first) : $this->notAfter($first->subDays(13)->setTime(11, 0));
    }

    /**
     * The moment, moved off a weekend to the Monday after, or the given
     * number of days ago if that is earlier, so a demo decision lands on
     * a working day and never in the future.
     */
    private function notAfter(CarbonInterface $moment, int $daysAgo = 1): CarbonInterface
    {
        $onAWeekday = $moment->isWeekend() ? $moment->next(CarbonInterface::MONDAY)->setTimeFrom($moment) : $moment;

        return $onAWeekday->min(now()->subDays($daysAgo));
    }

    /**
     * Decline the first Pending Work Entry on the Assignment on or after
     * the date, as the decliner, with the reason.
     */
    private function decline(Assignment $assignment, CarbonInterface $date, Employee $decliner, string $reason): void
    {
        $workEntry = $assignment->workEntries()->pending()->whereDate('date', '>=', $date)->orderBy('date')->firstOrFail();

        $workEntry->forceFill([
            'state' => WorkEntryState::Declined,
            'declined_by' => $decliner->id,
            'declined_at' => $this->notAfter($workEntry->date->toImmutable()->addDay()->setTime(9, 0), 0),
            'decline_reason' => $reason,
        ])->save();
    }

    /**
     * Give the Employee Paid Days, which every year gets, past ones included.
     */
    private function paidDays(Employee $employee, int $days): void
    {
        $employee->paid_days = $days;
        $employee->save();
    }

    /**
     * Add Time Off from a first date to the given number of days after it,
     * Pending unless given another state. Approved and Declined Time Off is
     * decided by the decider, or by nobody for an Employee with no Manager.
     * It was requested two weeks before its first day and decided the day
     * after, or cancelled three days before it, never later than recently.
     */
    private function timeOff(Employee $employee, CarbonInterface $first, int $daysAfter, bool $isPaid, TimeOffState $state = TimeOffState::Pending, ?Employee $decider = null, ?string $declineReason = null): void
    {
        $timeOff = $employee->timeOff()->make([
            'first_date' => $first,
            'last_date' => $first->addDays($daysAfter),
            'is_paid' => $isPaid,
        ]);

        $timeOff->forceFill([
            'state' => $state,
            'decided_by' => $decider?->id,
            'decided_at' => in_array($state, [TimeOffState::Approved, TimeOffState::Declined], true) ? $this->decidedAt($first, $decider) : null,
            'decline_reason' => $declineReason,
            'cancelled_at' => $state === TimeOffState::Cancelled ? $this->notAfter($first->subDays(3)->setTime(15, 0)) : null,
            'created_at' => $this->requestedAt($first),
        ])->save();
    }

    /**
     * A year of Ben's travel, decided by Ana, his Manager, so his Travel
     * page looks the way it would after months of use: a dozen trips
     * behind him and a handful ahead, on Relaunch and his Internal
     * Assignment, in every state, with Travel Expenses in every state on
     * those that take them. The older ones are Approved; the latest still
     * wait for her. None shares a day with his Time Off, four weeks from
     * this Monday.
     */
    private function bensTravel(Assignment $relaunch, Assignment $internal, Employee $ana, CarbonInterface $thisMonday, CarbonInterface $today): void
    {
        $approvedTrip = TravelRequestState::Approved;
        [$transport, $accommodation, $meals, $other] = [TravelExpenseCategory::Transport, TravelExpenseCategory::Accommodation, TravelExpenseCategory::Meals, TravelExpenseCategory::Other];
        [$pendingExpense, $approvedExpense, $declinedExpense] = [TravelExpenseState::Pending, TravelExpenseState::Approved, TravelExpenseState::Declined];

        /**
         * Each trip: Assignment, destination, first date, days after it,
         * purpose, state, decision, decline reason, and its Travel
         * Expenses as [when it was paid, as a day of the trip or a date,
         * category, amount in cents, description, state, decline reason].
         *
         * @var array<int, array{Assignment, string, CarbonInterface, int, string, TravelRequestState, bool, string|null, array<int, array{int|CarbonInterface, TravelExpenseCategory, int, string, TravelExpenseState, string|null}>}> $trips
         */
        $trips = [
            [$relaunch, 'Berlin', $thisMonday->subWeeks(48)->addDay(), 2, 'Relaunch kickoff with Globex', $approvedTrip, true, null, [
                [0, $transport, 24500, 'Train to Berlin and back', $approvedExpense, null],
                [2, $accommodation, 61200, 'Hotel, two nights', $approvedExpense, null],
                [1, $meals, 8900, 'Dinner on the road', $approvedExpense, null],
            ]],
            [$internal, 'Munich', $thisMonday->subWeeks(43)->addDays(2), 1, 'Engineering hiring day', $approvedTrip, true, null, [
                [0, $transport, 15800, 'Train to Munich and back', $approvedExpense, null],
                [1, $meals, 4200, 'Lunch between interviews', $approvedExpense, null],
            ]],
            [$internal, 'London', $thisMonday->subWeeks(39), 3, 'Engineering leadership offsite', $approvedTrip, true, null, [
                [0, $transport, 41000, 'Flights to London and back', $approvedExpense, null],
                [3, $accommodation, 96000, 'Hotel, three nights', $approvedExpense, null],
                [1, $other, 12000, 'Offsite venue, my share', $approvedExpense, null],
                [2, $meals, 6500, 'Team dinner at the offsite', $declinedExpense, 'The offsite dinner was paid for by the company.'],
            ]],
            [$relaunch, 'Berlin', $thisMonday->subWeeks(35)->addDay(), 2, 'Relaunch roadmap review', TravelRequestState::Declined, true, 'We can do the roadmap review over video this time.', []],
            [$internal, 'Amsterdam', $thisMonday->subWeeks(31)->addDays(2), 2, 'Engineering conference', $approvedTrip, true, null, [
                [0, $other, 89000, 'Conference ticket', $approvedExpense, null],
                [0, $transport, 32000, 'Flights to Amsterdam and back', $approvedExpense, null],
                [2, $accommodation, 54000, 'Hotel, two nights', $approvedExpense, null],
            ]],
            [$relaunch, 'Berlin', $thisMonday->subWeeks(27)->addDay(), 1, 'Sprint review with Globex', TravelRequestState::Cancelled, false, null, []],
            [$relaunch, 'Berlin', $thisMonday->subWeeks(24)->addDay(), 2, 'Checkout design sprint', $approvedTrip, true, null, [
                [0, $transport, 19800, 'Train to Berlin and back', $approvedExpense, null],
                [2, $accommodation, 38000, 'Hotel, two nights', $approvedExpense, null],
                [1, $meals, 7600, 'Lunch with the design team', $approvedExpense, null],
            ]],
            [$internal, 'Vienna', $thisMonday->subWeeks(20)->addDays(3), 1, 'Meeting a candidate team lead', TravelRequestState::Cancelled, true, null, [
                [0, $transport, 21000, 'Train tickets, bought before the meeting moved online', $approvedExpense, null],
                [0, $accommodation, 9500, 'Hotel cancellation fee', $approvedExpense, null],
            ]],
            [$relaunch, 'Berlin', $thisMonday->subWeeks(16)->addDay(), 3, 'Load testing with Globex ops', $approvedTrip, true, null, [
                [0, $transport, 22400, 'Train to Berlin and back', $approvedExpense, null],
                [3, $accommodation, 58500, 'Hotel, three nights', $approvedExpense, null],
                [2, $meals, 11200, 'Dinner with the Globex ops team', $declinedExpense, self::CLIENT_DINNER_DECLINED],
                [1, $other, 3500, 'Taxi to the data centre', $pendingExpense, null],
            ]],
            [$internal, 'Paris', $thisMonday->subWeeks(11)->addDays(2), 2, 'Recruiting fair', $approvedTrip, true, null, [
                [0, $transport, 27000, 'Train to Paris and back', $approvedExpense, null],
                [2, $accommodation, 41000, 'Hotel, two nights', $pendingExpense, null],
            ]],
            [$relaunch, 'Munich', $thisMonday->subWeeks(7)->addDay(), 1, 'Payment provider workshop', $approvedTrip, true, null, [
                [0, $transport, 16200, 'Train to Munich and back', $pendingExpense, null],
                [1, $meals, 3900, 'Lunch at the workshop', $pendingExpense, null],
            ]],
            [$relaunch, 'Berlin', $thisMonday->subWeeks(2)->addDay(), 2, 'Relaunch beta check-in', $approvedTrip, true, null, [
                [0, $transport, 19900, 'Train to Berlin and back', $pendingExpense, null],
                [2, $accommodation, 36000, 'Hotel, two nights', $pendingExpense, null],
                [1, $meals, 5200, 'Dinner on the road', $pendingExpense, null],
            ]],
            [$relaunch, 'Berlin', $thisMonday->addWeeks(2)->addDay(), 2, 'Relaunch go-live support', $approvedTrip, true, null, [
                [$today->subDays(2), $transport, 21500, 'Train to Berlin, booked early', $approvedExpense, null],
                [$today->subDay(), $accommodation, 34000, 'Hotel, two nights, paid when booking', $pendingExpense, null],
            ]],
            [$relaunch, 'Amsterdam', $thisMonday->addWeeks(3)->addDays(2), 1, 'Globex partner meetup', TravelRequestState::Cancelled, false, null, []],
            [$internal, 'Munich', $thisMonday->addWeeks(5)->addDays(2), 1, 'Team lead training', TravelRequestState::Pending, false, null, []],
            [$internal, 'London', $thisMonday->addWeeks(7)->addDay(), 3, 'Engineering leadership offsite', TravelRequestState::Pending, false, null, []],
            [$relaunch, 'Berlin', $thisMonday->addWeeks(10), 2, 'Quarterly planning with Globex', $approvedTrip, true, null, [
                [$today->subDays(4), $transport, 18900, 'Train to Berlin, booked early', $pendingExpense, null],
                [$today->subDays(4), $accommodation, 35000, 'Hotel, two nights, paid when booking', $pendingExpense, null],
            ]],
        ];

        foreach ($trips as [$assignment, $destination, $first, $daysAfter, $purpose, $state, $isDecided, $declineReason, $expenses]) {
            $travelRequest = $this->travelRequest($assignment, $destination, $first, $daysAfter, $purpose, $state, $isDecided ? $ana : null, $declineReason);

            foreach ($expenses as [$paidOn, $category, $amount, $description, $expenseState, $expenseDeclineReason]) {
                $this->travelExpense($travelRequest, is_int($paidOn) ? $first->addDays($paidOn) : $paidOn, $category, $amount, $description, $expenseState, $ana, $expenseDeclineReason);
            }
        }
    }

    /**
     * Create the Employee's Performance Evaluation for this year, Open, with
     * the Goals given as title, description, the changes to their
     * Progress, each as the Progress and who changed it, and optionally
     * their Comments, each as who wrote it and what. The Goals were given
     * up to two months ago, never before this year, and their changes and
     * Comments are spread evenly from then to today.
     *
     * @param  array<int, array{0: string, 1: string, 2: array<int, array{Progress, Employee}>, 3?: array<int, array{Employee, string}>}>  $goals
     */
    private function performanceEvaluation(Employee $employee, array $goals): void
    {
        $evaluation = $employee->performanceEvaluations()->create(['year' => today()->year]);
        $givenAt = today()->subMonths(2)->max(today()->startOfYear())->setTime(9, 0);
        $days = (int) $givenAt->diffInDays(today());

        foreach ($goals as $given) {
            [$title, $description, $changes] = $given;
            $comments = $given[3] ?? [];
            $goal = $evaluation->goals()->create(['title' => $title, 'description' => $description]);
            $goal->forceFill(['created_at' => $givenAt])->save();

            foreach ($changes as $position => [$progress, $changedBy]) {
                $this->changeGoalProgress->change($goal, $progress, $changedBy)
                    ?->forceFill(['created_at' => $givenAt->addDays(intdiv($days * ($position + 1), count($changes) + 1))->addHours($position + 1)])
                    ->save();
            }

            foreach ($comments as $position => [$author, $body]) {
                $goal->comments()->create(['author_id' => $author->id, 'body' => $body])
                    ->forceFill(['created_at' => $givenAt->addDays(intdiv($days * ($position + 1), count($comments) + 1))->addMinutes(30 * ($position + 1))])
                    ->save();
            }
        }
    }

    /**
     * Add the Employee's Performance Evaluation for last year, Completed,
     * with the Goals given as title, description and Outcome, and the
     * Outcome and summary of the whole.
     *
     * @param  array<int, array{string, string, Outcome}>  $goals
     */
    private function completedPerformanceEvaluation(Employee $employee, array $goals, Outcome $outcome, string $summary): void
    {
        $evaluation = $employee->performanceEvaluations()->create(['year' => today()->year - 1]);

        foreach ($goals as [$title, $description, $goalOutcome]) {
            $this->assessPerformanceEvaluation->giveGoalOutcome($evaluation->goals()->create(['title' => $title, 'description' => $description]), $goalOutcome);
        }

        $this->assessPerformanceEvaluation->writeSummary($evaluation, $summary);
        $this->assessPerformanceEvaluation->complete($evaluation, $outcome);
    }

    /**
     * Add a Travel Request on the Assignment from a first date to the given
     * number of days after it, Pending unless given another state. Approved
     * and Declined ones are decided by the decider, or by nobody for an
     * Employee with no Manager; a Cancelled one given a decider was Approved
     * by them first. It was made two weeks before its first day and
     * decided the day after, or cancelled three days before it, never
     * later than recently.
     */
    private function travelRequest(Assignment $assignment, string $destination, CarbonInterface $first, int $daysAfter, string $purpose, TravelRequestState $state = TravelRequestState::Pending, ?Employee $decider = null, ?string $declineReason = null): TravelRequest
    {
        $travelRequest = $assignment->employee->travelRequests()->make([
            'assignment_id' => $assignment->id,
            'destination' => $destination,
            'first_date' => $first,
            'last_date' => $first->addDays($daysAfter),
            'purpose' => $purpose,
        ]);

        $travelRequest->forceFill([
            'state' => $state,
            'decided_by' => $decider?->id,
            'decided_at' => in_array($state, [TravelRequestState::Approved, TravelRequestState::Declined], true) || ($state === TravelRequestState::Cancelled && $decider !== null) ? $this->decidedAt($first, $decider) : null,
            'decline_reason' => $declineReason,
            'cancelled_at' => $state === TravelRequestState::Cancelled ? $this->notAfter($first->subDays(3)->setTime(15, 0)) : null,
            'created_at' => $this->requestedAt($first),
        ])->save();

        return $travelRequest;
    }

    /**
     * Add a Travel Expense of the given amount in cents on the Travel
     * Request, with a one-page PDF receipt naming it, Pending unless given
     * another state. Approved ones are approved by the decider, or by
     * nobody for an Employee with no Manager; Declined ones are declined by
     * the decider with the reason, a few days after the trip, or after
     * it was paid if later, but never later than recently.
     */
    private function travelExpense(TravelRequest $travelRequest, CarbonInterface $paidOn, TravelExpenseCategory $category, int $amount, string $description, TravelExpenseState $state = TravelExpenseState::Pending, ?Employee $decider = null, ?string $declineReason = null): void
    {
        $receiptPath = TravelExpense::RECEIPT_FOLDER.'/'.Str::uuid()->toString().'.pdf';
        Storage::disk(TravelExpense::RECEIPT_DISK)->put($receiptPath, $this->receiptPdf("Receipt: {$description}"));

        $travelExpense = $travelRequest->travelExpenses()->make([
            'paid_on' => $paidOn,
            'amount' => $amount,
            'category' => $category,
            'description' => $description,
            'receipt_path' => $receiptPath,
        ]);

        $decidedAt = $this->notAfter($paidOn->max($travelRequest->last_date->toImmutable())->addDays(3)->setTime(10, 0));

        $travelExpense->forceFill([
            'state' => $state,
            'approved_by' => $state === TravelExpenseState::Approved ? $decider?->id : null,
            'approved_at' => $state === TravelExpenseState::Approved ? $decidedAt : null,
            'declined_by' => $state === TravelExpenseState::Declined ? $decider?->id : null,
            'declined_at' => $state === TravelExpenseState::Declined ? $decidedAt : null,
            'decline_reason' => $declineReason,
        ])->save();
    }

    /**
     * A minimal one-page PDF showing one line of text.
     */
    private function receiptPdf(string $line): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $content = "BT /F1 14 Tf 40 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%EOF\n";
    }

    /**
     * @param  list<array{0: string, 1: int, 2: CarbonInterface|null}>  $entries
     */
    private function pay(Assignment $assignment, array $entries): void
    {
        foreach ($entries as [$kind, $amount, $effectiveFrom]) {
            $assignment->payEntries()->create([
                'kind' => PayKind::from($kind),
                'amount' => $amount,
                'effective_from' => $effectiveFrom ?? $assignment->start_date,
            ]);
        }
    }
}
