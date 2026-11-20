<?php

namespace App\Http\Requests\Travel;

use App\Models\Assignment;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TravelRequestRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignment_id' => ['bail', 'required', 'integer', Rule::exists(Assignment::class, 'id')->where('employee_id', $this->employee()->id)],
            'destination' => ['required', 'string', 'max:255'],
            'first_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'last_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:first_date'],
            'purpose' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkAssignmentDates($validator);
            $this->checkTravelOverlap($validator);
            $this->checkTimeOffOverlap($validator);
        }];
    }

    /** The Assignment falls within the Employee's period of work, so this keeps the travel within it too. */
    private function checkAssignmentDates(Validator $validator): void
    {
        $assignment = Assignment::query()->with('project:id,name')->findOrFail($this->integer('assignment_id'));
        $replace = [
            'project' => $assignment->project->name,
            'start' => $assignment->start_date->format('j M Y'),
            'end' => $assignment->end_date?->format('j M Y'),
        ];

        if ($this->firstDate()->isBefore($assignment->start_date)) {
            $validator->errors()->add('first_date', __('Your Assignment to :project starts on :start, so a Travel Request can\'t start before it.', $replace));
        }

        if ($assignment->end_date !== null && $this->lastDate()->isAfter($assignment->end_date)) {
            $validator->errors()->add('last_date', __('Your Assignment to :project ends on :end, so a Travel Request must end by then.', $replace));
        }
    }

    private function checkTravelOverlap(Validator $validator): void
    {
        $overlapped = $this->employee()->travelRequests()
            ->standing()
            ->overlapping($this->firstDate(), $this->lastDate())
            ->orderBy('first_date')
            ->first();

        if ($overlapped !== null) {
            $validator->errors()->add('first_date', __('You already have a Travel Request from :first to :last, so these dates can\'t overlap it.', [
                'first' => $overlapped->first_date->format('j M Y'),
                'last' => $overlapped->last_date->format('j M Y'),
            ]));
        }
    }

    private function checkTimeOffOverlap(Validator $validator): void
    {
        $overlapped = $this->employee()->timeOff()
            ->standing()
            ->overlapping($this->firstDate(), $this->lastDate())
            ->orderBy('first_date')
            ->first();

        if ($overlapped !== null) {
            $validator->errors()->add('first_date', __('You have Time Off from :first to :last, so a Travel Request can\'t overlap it.', [
                'first' => $overlapped->first_date->format('j M Y'),
                'last' => $overlapped->last_date->format('j M Y'),
            ]));
        }
    }

    /** @return array{assignment_id: int, destination: string, first_date: string, last_date: string, purpose: string} */
    public function travelRequest(): array
    {
        return [
            'assignment_id' => $this->integer('assignment_id'),
            'destination' => $this->string('destination')->value(),
            'first_date' => $this->string('first_date')->value(),
            'last_date' => $this->string('last_date')->value(),
            'purpose' => $this->string('purpose')->value(),
        ];
    }

    public function employee(): Employee
    {
        /** @var Employee */
        return $this->user();
    }

    private function firstDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('first_date')->value());
    }

    private function lastDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('last_date')->value());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignment_id.required' => __('Choose the Assignment this Travel Request is for.'),
            'assignment_id.integer' => __('Choose one of your Assignments.'),
            'assignment_id.exists' => __('Choose one of your Assignments.'),
            'first_date.after_or_equal' => __('Travel Requests can\'t start in the past.'),
        ];
    }
}
