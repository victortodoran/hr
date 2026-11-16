<?php

namespace Database\Seeders;

use App\Actions\AddEmployee;
use App\Actions\ChangeAccess;
use App\Actions\InstallCompany;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\PayKind;
use App\Enums\Permission;
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
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small demo company with at least one of everything, so every flow can be
 * tried by hand. It installs the company the way hr:install does, then adds
 * Employees, Roles, Clients, Projects, Assignments and Pay through the same
 * actions the app uses, four weeks of Work Entries, Paid Days (none for
 * Ivan), Time Off and Travel Requests in every state, and Travel Expenses
 * with small generated PDF receipts, replacing any an earlier demo left
 * behind. Dates are relative to today, so the
 * Future and Former Employees stay that way, and the last two weeks of Work
 * Entries and the Time Off still to come are always Pending, waiting for
 * each Employee's Manager (Ben or Hana) to approve them. Everyone except the
 * Future Employee can sign in with the password "password"; the Future
 * Employee still has an invitation waiting. Invitations are not emailed.
 *
 *   Ana (no Manager, Admin)
 *   ├── Ben (Senior Manager: Pay of his Reports)
 *   │   ├── Cara (Employee; Client work and a planned raise)
 *   │   ├── Dan (Future Employee, invitation pending)
 *   │   └── Eve (Former Employee)
 *   │       └── Lia (her Manager has left, so Ben is her effective Manager)
 *   ├── Hana (Manager: no Pay)
 *   │   └── Ivan (leaving in six months)
 *   └── Finn (Payroll: everyone's Pay)
 */
class DemoSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    public function __construct(
        private InstallCompany $installCompany,
        private AddEmployee $addEmployee,
        private ChangeAccess $changeAccess,
        private UpdateTrimmingAssignments $updateTrimmingAssignments,
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
        $this->letSignIn($ana);

        $engineering = Department::query()->where('name', 'Engineering')->sole();
        $design = Department::query()->create(['name' => 'Design']);
        $finance = Department::query()->create(['name' => 'Finance']);
        Department::query()->create(['name' => 'Legal']);

        $seniorManager = $this->changeAccess->createRole($ana, 'Senior Manager', [
            Permission::ViewDirectory->value => null,
            Permission::ViewEmployeeRecords->value => Scope::Reports->value,
            Permission::ViewAssignments->value => Scope::Reports->value,
            Permission::ManageAssignments->value => Scope::Reports->value,
            Permission::ViewPay->value => Scope::Reports->value,
            Permission::ViewTimeOff->value => Scope::Reports->value,
            Permission::ViewTravel->value => Scope::Reports->value,
            Permission::ViewProjects->value => null,
        ], givenToNewEmployees: false);
        $payroll = $this->changeAccess->createRole($ana, 'Payroll', [
            Permission::ViewPay->value => Scope::Everyone->value,
            Permission::ManagePay->value => Scope::Everyone->value,
        ], givenToNewEmployees: false);
        $this->changeAccess->createRole($ana, 'Auditor', [
            Permission::ViewDirectory->value => null,
            Permission::ViewProjects->value => null,
        ], givenToNewEmployees: false);

        $ben = $this->add($ana, 'Ben Ionescu', $engineering, $ana, $today->subYears(3));
        $hana = $this->add($ana, 'Hana Pop', $design, $ana, $today->subYears(2));
        $finn = $this->add($ana, 'Finn Muller', $finance, $ana, $today->subYears(2));
        $cara = $this->add($ana, 'Cara Lane', $engineering, $ben, $today->subMonths(18));
        $eve = $this->add($ana, 'Eve Stan', $engineering, $ben, $today->subYears(2));
        $lia = $this->add($ana, 'Lia Rusu', $engineering, $eve, $today->subYear());
        $ivan = $this->add($ana, 'Ivan Petrov', $design, $hana, $today->subYear(), $today->addMonths(6));
        $dan = $this->add($ana, 'Dan Moraru', $engineering, $ben, $today->addMonth(), canSignIn: false);
        $this->updateTrimmingAssignments->updateEmployee($eve, ['leaving_date' => $today->subMonths(2)]);

        $this->changeAccess->giveRole($ana, $seniorManager, $ben);
        $this->changeAccess->giveRole($ana, Role::query()->where('name', 'Manager')->sole(), $hana);
        $this->changeAccess->giveRole($ana, $payroll, $finn);

        $globex = Client::query()->create(['name' => 'Globex']);
        $initech = Client::query()->create(['name' => 'Initech']);
        Client::query()->create(['name' => 'Umbrella']);

        $relaunch = $this->project('Relaunch', $globex, $today->subYear());
        $audit = $this->project('Data Audit', $globex, $today->subMonths(14), $today->subMonths(2));
        $mobileApp = $this->project('Mobile App', $initech, $today->addMonths(2));
        $website = $this->project('Website Revamp', null, $today->subMonths(6), $today->addMonths(4));
        $this->project('Archive Cleanup', null, $today->subMonth());

        $this->internal($ana, 'CTO', 100, [['fixed', 900000, null]]);
        $this->internal($ben, 'Engineering Manager', 80, [['fixed', 650000, null]]);
        $this->internal($hana, 'Design Lead', 100, [['fixed', 580000, null]]);
        $this->internal($finn, 'Payroll Specialist', 100, [['fixed', 450000, null]]);
        $this->internal($cara, 'Senior Developer', 0, [
            ['fixed', 420000, null],
            ['fixed', 480000, $today->subMonths(3)],
            ['fixed', 520000, $today->addMonths(2)],
        ]);
        $this->internal($eve, 'QA Engineer', 0, [['fixed', 400000, null]]);
        $this->internal($lia, 'Developer', 20, [['fixed', 350000, null]]);
        $this->internal($ivan, 'Product Designer', 50, [['fixed', 390000, null]]);
        $this->internal($dan, null, 0, []);

        $caraOnRelaunch = $this->assign($cara, $relaunch, 'Tech Lead', 60, $today->subYear(), null, [['hourly', 2500, null]]);
        $this->assign($cara, $audit, 'Developer', 40, $today->subMonths(14), $today->subMonths(2), [['hourly', 2200, null]]);
        $this->assign($ben, $relaunch, 'Delivery Lead', 20, $today->subYear(), null, []);
        $this->assign($eve, $audit, 'QA Engineer', 100, $today->subMonths(14), $today->subMonths(2), [['hourly', 2000, null]]);
        $liaOnRelaunch = $this->assign($lia, $relaunch, 'Developer', 80, $today->subMonths(6), null, [['hourly', 1800, null]]);
        $ivanOnWebsite = $this->assign($ivan, $website, 'Designer', 50, $today->subMonths(6), $today->addMonths(4), [['hourly', 3000, null]]);
        $this->assign($dan, $mobileApp, 'Developer', 100, $today->addMonths(2), null, []);

        $this->recordWeeks($caraOnRelaunch, 5, ['Reviewing pull requests', 'Checkout API', 'Sprint planning', 'Fixing payment bugs'], $ben, $finn);
        $this->decline($caraOnRelaunch, today()->startOfWeek(CarbonInterface::MONDAY)->subWeek(), $ben, 'Sprint planning goes on Internal, not Relaunch');
        $this->recordWeeks($liaOnRelaunch, 6, ['Product listing page', 'Unit tests for the cart', 'Pairing with Cara'], $ben, $finn);
        $this->recordWeeks($ivanOnWebsite, 4, ['Homepage mock-ups', 'Design review with Hana', 'Icon set'], $hana, $finn);

        foreach ([$ana, $ben, $hana, $finn, $cara, $eve, $lia, $dan] as $employee) {
            $this->paidDays($employee, $employee->manager_id === null ? 25 : 20);
        }
        $this->paidDays($ivan, 0);

        $thisMonday = $today->startOfWeek(CarbonInterface::MONDAY);
        $this->timeOff($ana, $thisMonday->subWeeks(6), 4, true, TimeOffState::Approved);
        $this->timeOff($ben, $thisMonday->addWeeks(4), 4, true, TimeOffState::Approved, $ana);
        $this->timeOff($cara, $thisMonday->subWeeks(5), 2, true, TimeOffState::Approved, $ben);
        $this->timeOff($cara, $thisMonday->addWeeks(2), 4, true);
        $this->timeOff($cara, $thisMonday->addWeeks(5)->addDays(3), 1, true, TimeOffState::Cancelled);
        $this->timeOff($lia, $thisMonday->addWeeks(3), 1, false);
        $this->timeOff($ivan, $thisMonday->addWeeks(2), 2, false, TimeOffState::Declined, $hana, 'Launch week for the Website Revamp. Could you take the week after?');
        $this->timeOff($ivan, $thisMonday->addWeeks(3)->addDays(4), 0, false);

        $workshop = $this->travelRequest($caraOnRelaunch, 'Berlin', $thisMonday->subWeeks(3)->addDay(), 2, 'Checkout API workshop with Globex', TravelRequestState::Approved, $ben);
        $this->travelRequest($caraOnRelaunch, 'Berlin', $thisMonday->addWeeks(3)->addDay(), 2, 'Relaunch go-live with Globex');
        $this->travelRequest($caraOnRelaunch, 'Munich', $thisMonday->addWeeks(1)->addDays(2), 1, 'Relaunch demo, moved online', TravelRequestState::Cancelled);
        $this->travelRequest($liaOnRelaunch, 'Berlin', $thisMonday->addWeeks(1)->addDays(3), 3, 'Pairing on the cart with the Globex team');
        $this->travelRequest($ivanOnWebsite, 'Lisbon', $thisMonday->addWeek(), 1, 'Design conference', TravelRequestState::Declined, $hana, 'Launch prep that week. Could you watch the talks online?');
        $investors = $this->travelRequest($ana->internalAssignment()->sole(), 'London', $thisMonday->addWeeks(6), 4, 'Meeting investors', TravelRequestState::Approved);

        $this->travelExpense($workshop, $workshop->first_date, TravelExpenseCategory::Transport, 18900, 'Train to Berlin and back', TravelExpenseState::Approved, $ben);
        $this->travelExpense($workshop, $workshop->last_date, TravelExpenseCategory::Accommodation, 39800, 'Hotel, two nights');
        $this->travelExpense($workshop, $workshop->first_date->addDay(), TravelExpenseCategory::Meals, 14250, 'Team dinner with Globex', TravelExpenseState::Declined, $ben, 'Client dinners go through the company card. Could you add only your own meal?');
        $this->travelExpense($investors, $today->subDays(3), TravelExpenseCategory::Transport, 32000, 'Flights to London, booked early', TravelExpenseState::Approved);

        $this->command?->info('Demo company seeded. Sign in as ana@acme.test (Admin), ben@acme.test, hana@acme.test, finn@acme.test, cara@acme.test, ivan@acme.test or lia@acme.test with the password "password".');
    }

    /**
     * Add an Employee the way the app does, then, unless they are still to
     * accept their invitation, let them sign in with the demo password.
     */
    private function add(Employee $actor, string $name, Department $department, Employee $manager, CarbonInterface $startDate, ?CarbonInterface $leavingDate = null, bool $canSignIn = true): Employee
    {
        $employee = $this->addEmployee->add($actor, [
            'name' => $name,
            'email' => Str::lower(Str::before($name, ' ')).'@acme.test',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
            'start_date' => $startDate,
            'leaving_date' => $leavingDate,
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
     * Set the Employee's internal Job Title and Capacity, and give their
     * Internal Assignment its Pay history.
     *
     * @param  list<array{0: string, 1: int, 2: CarbonInterface|null}>  $pay  Kind, amount in cents, and the day it takes effect (null for the first day)
     */
    private function internal(Employee $employee, ?string $jobTitle, int $capacity, array $pay): void
    {
        $internal = $employee->internalAssignment()->sole();
        $internal->update(['job_title' => $jobTitle, 'capacity' => $capacity]);

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
     * Record hours on the Assignment for every weekday of the last four
     * weeks, up to today. This week and last are Pending. The week before was
     * Approved by the approver the following Monday, and the one before that
     * was also Locked by the locker a week later.
     *
     * @param  list<string>  $descriptions  What was done, taken in turn
     */
    private function recordWeeks(Assignment $assignment, int $hoursPerDay, array $descriptions, Employee $approver, Employee $locker): void
    {
        $thisMonday = today()->startOfWeek(CarbonInterface::MONDAY);

        foreach (range(0, 3) as $weeksAgo) {
            $monday = $thisMonday->subWeeks($weeksAgo);

            foreach (range(0, 4) as $weekday) {
                $date = $monday->addDays($weekday);

                if ($date->isAfter(today())) {
                    continue;
                }

                $workEntry = WorkEntry::query()->create([
                    'assignment_id' => $assignment->id,
                    'date' => $date,
                    'minutes' => $hoursPerDay * 60,
                    'description' => $descriptions[($weeksAgo * 5 + $weekday) % count($descriptions)],
                ]);

                if ($weeksAgo >= 2) {
                    $workEntry->forceFill([
                        'state' => $weeksAgo === 3 ? WorkEntryState::Locked : WorkEntryState::Approved,
                        'approved_by' => $approver->id,
                        'approved_at' => $monday->addWeek()->setTime(10, 0),
                        'locked_by' => $weeksAgo === 3 ? $locker->id : null,
                        'locked_at' => $weeksAgo === 3 ? $monday->addWeeks(2)->setTime(11, 0) : null,
                    ])->save();
                }
            }
        }
    }

    /**
     * Decline the Pending Work Entry on the Assignment on the date, as the
     * decliner, with the reason.
     */
    private function decline(Assignment $assignment, CarbonInterface $date, Employee $decliner, string $reason): void
    {
        $assignment->workEntries()->whereDate('date', $date)->sole()->forceFill([
            'state' => WorkEntryState::Declined,
            'declined_by' => $decliner->id,
            'declined_at' => $date->addDay()->setTime(9, 0),
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
            'decided_at' => in_array($state, [TimeOffState::Approved, TimeOffState::Declined], true) ? now()->subDay() : null,
            'decline_reason' => $declineReason,
            'cancelled_at' => $state === TimeOffState::Cancelled ? now()->subDay() : null,
        ])->save();
    }

    /**
     * Add a Travel Request on the Assignment from a first date to the given
     * number of days after it, Pending unless given another state. Approved
     * and Declined ones are decided by the decider, or by nobody for an
     * Employee with no Manager.
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
            'decided_at' => in_array($state, [TravelRequestState::Approved, TravelRequestState::Declined], true) ? now()->subDay() : null,
            'decline_reason' => $declineReason,
            'cancelled_at' => $state === TravelRequestState::Cancelled ? now()->subDay() : null,
        ])->save();

        return $travelRequest;
    }

    /**
     * Add a Travel Expense of the given amount in cents on the Travel
     * Request, with a one-page PDF receipt naming it, Pending unless given
     * another state. Approved ones are approved by the decider, or by
     * nobody for an Employee with no Manager; Declined ones are declined by
     * the decider with the reason.
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

        $travelExpense->forceFill([
            'state' => $state,
            'approved_by' => $state === TravelExpenseState::Approved ? $decider?->id : null,
            'approved_at' => $state === TravelExpenseState::Approved ? now()->subDay() : null,
            'declined_by' => $state === TravelExpenseState::Declined ? $decider?->id : null,
            'declined_at' => $state === TravelExpenseState::Declined ? now()->subDay() : null,
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
