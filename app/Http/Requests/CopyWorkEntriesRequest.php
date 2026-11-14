<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The day to copy an Employee's Work Entries onto, from the latest of the
 * days before it, at most a week back, that has any. Only Work Entries
 * whose Assignment runs on the day are copied; nothing is copied onto a
 * day still to come, a day of Time Off, or past 24 hours on the day.
 */
class CopyWorkEntriesRequest extends FormRequest
{
    /** @var Collection<int, WorkEntry>|null */
    private ?Collection $toCopy = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * Once the date is valid on its own, check there is something to copy
     * onto it.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('date')) {
                return;
            }

            $date = $this->day();
            $replace = ['name' => $this->employee()->name, 'date' => $date->format('j M Y')];

            if ($date->isWeekday() && $this->employee()->timeOff()->standing()->overlapping($date, $date)->exists()) {
                $validator->errors()->add('date', __(':name has Time Off on :date, so nothing is copied onto it.', $replace));

                return;
            }

            $source = $this->sourceDate();

            if ($source === null) {
                $validator->errors()->add('date', __(':name has no Work Entries in the :days days before :date to copy.', [...$replace, 'days' => WorkEntry::COPY_LOOKBACK_DAYS]));

                return;
            }

            if ($this->toCopy()->isEmpty()) {
                $validator->errors()->add('date', __('None of :name\'s Work Entries on :source are on an Assignment that runs on :date.', [...$replace, 'source' => $source->format('D j')]));

                return;
            }

            $recorded = (int) $this->employee()->workEntries()->whereDate('work_entries.date', $date)->sum('minutes');
            $copying = (int) $this->toCopy()->sum('minutes');

            if ($recorded + $copying > WorkEntry::MINUTES_IN_A_DAY) {
                $validator->errors()->add('date', __('Copying :copying hours would take :name past 24 hours on :date, where :recorded are already recorded.', [
                    ...$replace,
                    'copying' => WorkEntry::formatHours($copying),
                    'recorded' => WorkEntry::formatHours($recorded),
                ]));
            }
        }];
    }

    /**
     * The day to copy onto.
     */
    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->value())->startOfDay();
    }

    /**
     * The latest day in the week before the date with any of the Employee's
     * Work Entries; null when there is none.
     */
    public function sourceDate(): ?CarbonImmutable
    {
        $latest = $this->employee()->workEntries()
            ->whereDate('work_entries.date', '>=', $this->day()->subDays(WorkEntry::COPY_LOOKBACK_DAYS))
            ->whereDate('work_entries.date', '<', $this->day())
            ->max('work_entries.date');

        return is_string($latest) ? CarbonImmutable::parse($latest)->startOfDay() : null;
    }

    /**
     * The source day's Work Entries whose Assignment runs on the date, in
     * the order they were recorded.
     *
     * @return Collection<int, WorkEntry>
     */
    public function toCopy(): Collection
    {
        $source = $this->sourceDate();

        return $this->toCopy ??= $source === null
            ? new Collection
            : $this->employee()->workEntries()
                ->whereDate('work_entries.date', $source)
                ->with('assignment')
                ->orderBy('work_entries.id')
                ->get()
                ->filter(fn (WorkEntry $entry): bool => $this->runsOn($entry->assignment, $this->day()))
                ->values();
    }

    private function runsOn(Assignment $assignment, CarbonImmutable $date): bool
    {
        return ! $date->isBefore($assignment->start_date)
            && ($assignment->end_date === null || ! $date->isAfter($assignment->end_date));
    }

    /**
     * The Employee whose Work Entries these are.
     */
    public function employee(): Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : throw new LogicException('Copying Work Entries always names an Employee.'); // @pest-mutate-ignore (the route always binds an Employee)
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => __('Work Entries can\'t be recorded for days still to come.'),
        ];
    }
}
