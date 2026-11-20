<?php

namespace App\Http\Requests\WorkEntries;

use App\Models\Employee;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class CopyWorkEntriesRequest extends FormRequest
{
    /** @var Collection<int, WorkEntry>|null */
    private ?Collection $toCopy = null;

    private ?CarbonImmutable $sourceDate = null;

    private bool $sourceDateFound = false;

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
     * No 24-hour check: a day holds at most 24 hours and Declined hours count nowhere, so copying onto a day with only Declined ones never goes past it.
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

            if ($this->employee()->timeOff()->standing()->onDay($date)->exists()) {
                $validator->errors()->add('date', __(':name has Time Off on :date, so nothing is copied onto it.', $replace));

                return;
            }

            if ($this->employee()->workEntries()->counted()->whereDate('work_entries.date', $date)->exists()) {
                $validator->errors()->add('date', __(':name already has Work Entries on :date; copying only fills a day with none.', $replace));

                return;
            }

            $source = $this->sourceDate();

            if ($source === null) {
                $validator->errors()->add('date', __(':name has no Work Entries in the :days days before :date to copy.', [...$replace, 'days' => WorkEntry::COPY_LOOKBACK_DAYS]));

                return;
            }

            if ($this->toCopy()->isEmpty()) {
                $validator->errors()->add('date', __('None of :name\'s Work Entries on :source are on an Assignment that runs on :date.', [...$replace, 'source' => $this->sourceLabel()]));
            }

        }];
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->value())->startOfDay();
    }

    public function sourceDate(): ?CarbonImmutable
    {
        if ($this->sourceDateFound) {
            return $this->sourceDate;
        }

        $latest = $this->employee()->workEntries()
            ->toCopyOnto($this->day())
            ->max('work_entries.date');

        $this->sourceDateFound = true;

        return $this->sourceDate = is_string($latest) ? CarbonImmutable::parse($latest)->startOfDay() : null;
    }

    public function sourceLabel(): string
    {
        $source = $this->sourceDate();

        return $source === null ? '' : $source->format($source->isSameMonth($this->day()) ? 'D j' : 'D j M');
    }

    /** @return Collection<int, WorkEntry> */
    public function toCopy(): Collection
    {
        if ($this->toCopy !== null) {
            return $this->toCopy;
        }

        $source = $this->sourceDate();

        return $this->toCopy = $source === null
            ? new Collection
            : $this->employee()->workEntries()
                ->counted()
                ->whereDate('work_entries.date', $source)
                ->whereHas('assignment', fn (Builder $running) => $running->runningOn($this->day()))
                ->orderBy('work_entries.id')
                ->get();
    }

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
