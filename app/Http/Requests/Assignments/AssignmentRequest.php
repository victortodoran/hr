<?php

namespace App\Http\Requests\Assignments;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Rules\WorkEntriesFit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * An Assignment's Project, Job Title, dates and Capacity, when putting
 * an Employee on a Project or changing an Assignment. The Project is chosen
 * once, when the Assignment is created. Every Assignment must fall within
 * its Employee's working period and its Project's dates. The Internal
 * Assignment only takes an internal Job Title, which is optional, and a
 * Capacity; its dates follow the Employee.
 */
class AssignmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $capacityRules = ['capacity' => ['required', 'integer', 'between:0,100']];

        if ($this->assignment()?->isInternal() === true) {
            return [
                'job_title' => ['nullable', 'string', 'max:255'],
                'start_date' => ['prohibited'],
                'end_date' => ['prohibited'],
                ...$capacityRules,
            ];
        }

        $projectRules = $this->assignment() === null
            ? ['project_id' => ['required', 'integer', Rule::exists(Project::class, 'id')->where('is_internal', false)]]
            : [];

        return [
            ...$projectRules,
            'job_title' => ['required', 'string', 'max:255'],
            'start_date' => ['bail', 'required', 'date', WorkEntriesFit::startOf($this->assignment()?->workEntries(), $this->workEntriesMessage(':name has Work Entries on this Assignment from :date, so it can\'t start later than that.'))],
            'end_date' => ['bail', 'nullable', 'date', 'after_or_equal:start_date', WorkEntriesFit::endOf($this->assignment()?->workEntries(), $this->workEntriesMessage(':name has Work Entries on this Assignment up to :date, so it can\'t end earlier than that.'))],
            ...$capacityRules,
        ];
    }

    /**
     * Once the Project and dates are valid on their own, check that they fit
     * the Employee and the Project.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['project_id', 'start_date', 'end_date'])) {
                return;
            }

            $this->checkDates($validator, $this->project(), $this->employee());
        }];
    }

    /**
     * Refuse a new Assignment on a finished Project, and any Assignment that
     * starts before, or may run past, the Employee's or the Project's dates.
     * The Internal Assignment takes the Employee's dates.
     */
    private function checkDates(Validator $validator, Project $project, Employee $employee): void
    {
        if ($this->assignment() === null && $project->hasFinished()) {
            $validator->errors()->add('project_id', __(':project has finished, so nobody can be assigned to it.', ['project' => $project->name]));

            return;
        }

        ['start_date' => $start, 'end_date' => $end] = $project->is_internal ? $employee->workingPeriod() : $this->period();

        foreach ([$employee, $project] as $owner) {
            foreach ($owner->assignmentRefusals($start, $end) as $field => $refusal) {
                $validator->errors()->add($field, $refusal);
            }
        }
    }

    /**
     * Why a date that would leave Work Entries outside the Assignment is
     * refused, naming its Employee and leaving :date for the rule.
     */
    private function workEntriesMessage(string $message): string
    {
        return __($message, ['name' => $this->employee()->name]);
    }

    /**
     * The dates the form asks for.
     *
     * @return array{start_date: Carbon, end_date: Carbon|null}
     */
    private function period(): array
    {
        return [
            'start_date' => Carbon::parse($this->string('start_date')->value()),
            'end_date' => $this->filled('end_date') ? Carbon::parse($this->string('end_date')->value()) : null,
        ];
    }

    /**
     * The Assignment being changed, or null when creating one.
     */
    private function assignment(): ?Assignment
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment ? $assignment : null; // @pest-mutate-ignore (the route parameter is always an Assignment or absent)
    }

    /**
     * The Employee the Assignment belongs to.
     */
    private function employee(): Employee
    {
        $employee = $this->assignment()->employee ?? $this->route('employee');

        return $employee instanceof Employee ? $employee : throw new LogicException('An Assignment always belongs to an Employee.'); // @pest-mutate-ignore (both routes always bind an Employee or an Assignment)
    }

    /**
     * The Project the Assignment is for: its own, or the one chosen.
     */
    private function project(): Project
    {
        return $this->assignment()->project ?? Project::query()->findOrFail($this->integer('project_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_id' => __('Project'),
            'job_title' => __('Job Title'),
            'capacity' => __('Capacity'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $internalDatesMessage = __('The Internal Assignment always runs from :name\'s Start Date to their Leaving Date.', ['name' => $this->employee()->name]);

        return [
            'start_date.prohibited' => $internalDatesMessage,
            'end_date.prohibited' => $internalDatesMessage,
        ];
    }
}
