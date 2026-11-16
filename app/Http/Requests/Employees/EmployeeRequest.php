<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Rules\AssignmentsFit;
use App\Rules\WorkEntriesFit;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An Employee's record, when adding or editing them.
 */
class EmployeeRequest extends FormRequest
{
    /**
     * Emails are compared and stored in lowercase, so capitals never make
     * two addresses different.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employee = $employee instanceof Employee ? $employee : null; // @pest-mutate-ignore (the route parameter is always an Employee or absent)

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Employee::class)->ignore($employee?->id)],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'manager_id' => ['bail', 'nullable', 'integer', Rule::exists(Employee::class, 'id'), Rule::notIn([$employee?->id]), $this->noLoopUnder($employee)],
            'start_date' => ['bail', 'required', 'date', AssignmentsFit::startOf($employee, ':name has an Assignment starting on :date, so their Start Date can\'t be later than that.'), WorkEntriesFit::startOf($employee?->workEntries(), __(':name has Work Entries from :date, so their Start Date can\'t be later than that.', ['name' => $employee?->name]))],
            'leaving_date' => match (true) {
                $employee?->employmentStatus() === EmploymentStatus::Former => [Rule::in([$employee->leaving_date?->toDateString()])], // @pest-mutate-ignore (a Former Employee always has a Leaving Date)
                default => ['bail', 'nullable', 'date', 'after_or_equal:start_date', AssignmentsFit::endOf($employee, ':name has an Assignment starting on :date, so their Leaving Date can\'t be earlier than that.'), WorkEntriesFit::endOf($employee?->workEntries(), __(':name has Work Entries up to :date, so their Leaving Date can\'t be earlier than that.', ['name' => $employee?->name])), $this->keepsSomeoneWhoCanManageRoles($employee)],
            },
        ];
    }

    /**
     * Refuses a new Manager who reports to the Employee, directly or not,
     * since that would turn the reporting line into a loop.
     */
    private function noLoopUnder(?Employee $employee): Closure
    {
        return function (string $attribute, mixed $managerId, Closure $fail) use ($employee): void {
            if ($employee !== null && $employee->hasInReports(Employee::query()->whereKey($managerId)->firstOrFail())) {
                $fail(__('That would make a loop: the new Manager reports to this Employee.'));
            }
        };
    }

    /**
     * Once the record is valid on its own, refuse a new Manager or Leaving
     * Date that would leave someone with Pending Work Entries or Pending Time
     * Off with no Manager to decide them: the Employee themselves, or one of
     * their Reports, whose effective Manager may come through them.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $employee = $this->route('employee');

            if ($validator->errors()->isNotEmpty() || ! $employee instanceof Employee) {
                return;
            }

            $managerId = $this->filled('manager_id') ? $this->integer('manager_id') : null;
            $leavingDate = $this->filled('leaving_date') ? $this->string('leaving_date')->value() : null;
            $leavingChanges = $leavingDate !== $employee->leaving_date?->toDateString();

            if ($managerId === $employee->manager_id && ! $leavingChanges) {
                return;
            }

            $stranded = $this->strandedBy($employee, $managerId, $leavingDate);

            if ($stranded?->is($employee)) {
                $validator->errors()->add('manager_id', __(':name has Pending Work Entries or Time Off, which nobody could decide without a Manager. Decide them first.', ['name' => $employee->name]));
            } elseif ($stranded !== null) {
                $validator->errors()->add($leavingChanges ? 'leaving_date' : 'manager_id', __(':name would have no Manager to decide their Pending Work Entries or Time Off. Give them a new Manager first.', ['name' => $stranded->name]));
            }
        }];
    }

    /**
     * Who, of the Employee and everyone below them, would be left with no
     * Manager while they have Pending Work Entries or Pending Time Off, once
     * the record is saved with this Manager and Leaving Date and, for a
     * Leaving Date, once it has passed. The change is tried in a transaction
     * that is always undone, so the reporting line is read exactly as the
     * rest of the app reads it. The Employee comes first, then by name.
     */
    private function strandedBy(Employee $employee, ?int $managerId, ?string $leavingDate): ?Employee
    {
        $on = $leavingDate !== null && CarbonImmutable::parse($leavingDate)->gte(today())
            ? CarbonImmutable::parse($leavingDate)->addDay()
            : CarbonImmutable::today();

        DB::beginTransaction();

        try {
            Employee::query()->whereKey($employee->id)->update(['manager_id' => $managerId, 'leaving_date' => $leavingDate]);
            $affected = Employee::query()->reportsOf($employee)->pluck('employees.id')->push($employee->id);

            return Employee::query()
                ->fromSub(Employee::query()->whereKey($affected)->withPendingDecisions()->withEffectiveManager($on), 'employees')
                ->whereNull('effective_manager_id')
                ->orderByRaw('id = ? desc', [$employee->id])
                ->orderBy('name')
                ->first();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Refuses a Leaving Date for the last Employee who can manage Roles,
     * since the company would lose the ability to change access.
     */
    private function keepsSomeoneWhoCanManageRoles(?Employee $employee): Closure
    {
        return function (string $attribute, mixed $leavingDate, Closure $fail) use ($employee): void {
            $lastingRoleManagers = Employee::query()->lastingRoleManagers();

            if ($employee !== null
                && (clone $lastingRoleManagers)->whereKey($employee->id)->exists()
                && ! $lastingRoleManagers->whereKeyNot($employee->id)->exists()) {
                $fail(__(':name is the last Employee who can manage Roles. Give manage roles to another current Employee before setting a Leaving Date.', ['name' => $employee->name]));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'department_id' => __('Department'),
            'manager_id' => __('Manager'),
            'start_date' => __('Start Date'),
            'leaving_date' => __('Leaving Date'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.not_in' => __('An Employee cannot be their own Manager.'),
            'leaving_date.in' => __('A Leaving Date that has passed can\'t be changed.'),
            'leaving_date.after_or_equal' => __('The Leaving Date must be on or after the Start Date.'),
        ];
    }
}
