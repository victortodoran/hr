<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * An Assignment's Project, Job Title, dates and share of time, when putting
 * an Employee on a Project or changing an Assignment. The Project is chosen
 * once, when the Assignment is created. Every Assignment must fall within
 * its Employee's working period and its Project's dates.
 */
class AssignmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $projectRules = $this->assignment() === null
            ? ['project_id' => ['required', 'integer', Rule::exists(Project::class, 'id')->where('is_internal', false)]]
            : [];

        return [
            ...$projectRules,
            'job_title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'share' => ['required', 'integer', 'between:1,100'],
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
     */
    private function checkDates(Validator $validator, Project $project, Employee $employee): void
    {
        if ($this->assignment() === null && $project->end_date?->isBefore(today())) {
            $validator->errors()->add('project_id', __(':project has finished, so nobody can be assigned to it.', ['project' => $project->name]));

            return;
        }

        $start = Carbon::parse($this->string('start_date')->value());
        $end = $this->filled('end_date') ? Carbon::parse($this->string('end_date')->value()) : null;

        if ($start->isBefore($employee->start_date)) {
            $validator->errors()->add('start_date', __(':name\'s Start Date is :date, so the Assignment can\'t start before it.', ['name' => $employee->name, 'date' => $this->readable($employee->start_date)]));
        }

        if ($project->start_date !== null && $start->isBefore($project->start_date)) { // @pest-mutate-ignore (only the Internal Project has no start date, and it is refused above)
            $validator->errors()->add('start_date', __(':project starts on :date, so the Assignment can\'t start before it.', ['project' => $project->name, 'date' => $this->readable($project->start_date)]));
        }

        if ($employee->leaving_date !== null && ($end === null || $end->isAfter($employee->leaving_date))) {
            $validator->errors()->add('end_date', __(':name\'s Leaving Date is :date, so the Assignment must end by then.', ['name' => $employee->name, 'date' => $this->readable($employee->leaving_date)]));
        }

        if ($project->end_date !== null && ($end === null || $end->isAfter($project->end_date))) {
            $validator->errors()->add('end_date', __(':project ends on :date, so the Assignment must end by then.', ['project' => $project->name, 'date' => $this->readable($project->end_date)]));
        }
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
     * A date as people read it, such as "2 Mar 2026".
     */
    private function readable(CarbonInterface $date): string
    {
        return $date->format('j M Y');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_id' => __('Project'),
            'job_title' => __('Job Title'),
            'share' => __('share of time'),
        ];
    }
}
