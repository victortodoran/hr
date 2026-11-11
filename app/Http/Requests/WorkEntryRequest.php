<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * A Work Entry's Assignment, date, hours and description, when recording
 * hours for an Employee or correcting a Work Entry. The Assignment must be
 * one of the Employee's own.
 */
class WorkEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', Rule::exists(Assignment::class, 'id')->where('employee_id', $this->employee()->id)],
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'hours' => ['bail', 'required', 'numeric', 'gt:0', 'max:24', 'multiple_of:0.25'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Once the Assignment and date are valid on their own, check that the
     * date falls within the Assignment.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['assignment_id', 'date'])) {
                return;
            }

            $assignment = Assignment::query()->with('project:id,name')->findOrFail($this->integer('assignment_id'));
            $date = Carbon::parse($this->string('date')->value());

            if ($date->isBefore($assignment->start_date) || ($assignment->end_date !== null && $date->isAfter($assignment->end_date))) {
                $validator->errors()->add('date', $this->outsideAssignment($assignment));

                return;
            }

            if (! $validator->errors()->has('hours')) {
                $this->checkDailyTotal($validator, $date);
            }
        }];
    }

    /**
     * Refuse hours that would take the Employee past 24 on the day, counting
     * their other Work Entries that day on every Assignment.
     */
    private function checkDailyTotal(Validator $validator, Carbon $date): void
    {
        $workEntry = $this->route('workEntry');
        $recorded = (int) $this->employee()->workEntries()
            ->whereDate('work_entries.date', $date)
            ->when($workEntry instanceof WorkEntry ? $workEntry->id : null, fn (Builder $others, int $id) => $others->whereKeyNot($id))
            ->sum('minutes');

        if ($recorded + $this->workEntry()['minutes'] > WorkEntry::MINUTES_IN_A_DAY) {
            $validator->errors()->add('hours', __(':name already has :recorded hours on :date, so at most :left more can be recorded that day.', [
                'name' => $this->employee()->name,
                'recorded' => WorkEntry::formatHours($recorded),
                'date' => $date->format('j M Y'),
                'left' => WorkEntry::formatHours(WorkEntry::MINUTES_IN_A_DAY - $recorded),
            ]));
        }
    }

    /**
     * Why a date outside the Assignment is refused.
     */
    private function outsideAssignment(Assignment $assignment): string
    {
        $replace = [
            'name' => $this->employee()->name,
            'project' => $assignment->project->name,
            'start' => $assignment->start_date->format('j M Y'),
            'end' => $assignment->end_date?->format('j M Y'),
        ];

        return $assignment->end_date === null
            ? __(':name\'s Assignment to :project starts on :start, so the date can\'t be before it.', $replace)
            : __(':name\'s Assignment to :project runs from :start to :end, so the date must fall within it.', $replace);
    }

    /**
     * The Work Entry as it is stored: its time in whole minutes.
     *
     * @return array{assignment_id: int, date: string, minutes: int, description: string}
     */
    public function workEntry(): array
    {
        return [
            'assignment_id' => $this->integer('assignment_id'),
            'date' => $this->string('date')->value(),
            'minutes' => (int) round($this->float('hours') * 60),
            'description' => $this->string('description')->value(),
        ];
    }

    /**
     * The Employee whose hours these are.
     */
    public function employee(): Employee
    {
        $workEntry = $this->route('workEntry');
        $employee = $workEntry instanceof WorkEntry ? $workEntry->assignment->employee : $this->route('employee');

        return $employee instanceof Employee ? $employee : throw new LogicException('A Work Entry always belongs to an Employee.'); // @pest-mutate-ignore (both routes always bind an Employee or a Work Entry)
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => __('Work Entries can\'t be recorded for days still to come.'),
            'hours.multiple_of' => __('Record hours in quarter-hour steps, such as 1.25 or 7.5.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assignment_id' => __('Assignment'),
        ];
    }
}
