<?php

namespace App\Http\Requests\TimeOff;

use App\Models\TimeOff;
use Illuminate\Validation\Validator;

class TimeOffRequest extends TimeOffPreviewRequest
{
    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkWorkingPeriod($validator);
            $this->checkWeekdays($validator);
            $this->checkOverlap($validator);
            $this->checkTravelOverlap($validator);
            $this->checkWorkedDays($validator);

            if ($validator->errors()->isEmpty() && $this->boolean('is_paid')) {
                $this->checkPaidDays($validator);
            }
        }];
    }

    private function checkWorkingPeriod(Validator $validator): void
    {
        $employee = $this->employee();

        if ($this->firstDate()->isBefore($employee->start_date)) { // @pest-mutate-ignore (EnsureEmployeeCanSignIn keeps Future Employees out, so the first date, today or later, is never before a signed-in Employee's Start Date)
            $validator->errors()->add('first_date', __('Your Start Date is :date, so Time Off can\'t start before it.', ['date' => $employee->start_date->format('j M Y')])); // @pest-mutate-ignore (unreachable for the same reason as the check above)
        }

        if ($employee->leaving_date !== null && $this->lastDate()->isAfter($employee->leaving_date)) {
            $validator->errors()->add('last_date', __('Your Leaving Date is :date, so Time Off must end by then.', ['date' => $employee->leaving_date->format('j M Y')]));
        }
    }

    private function checkWeekdays(Validator $validator): void
    {
        if (TimeOff::weekdaysBetween($this->firstDate(), $this->lastDate()) === 0) {
            $validator->errors()->add('first_date', __('Time Off must include at least one weekday; weekends don\'t count.'));
        }
    }

    private function checkOverlap(Validator $validator): void
    {
        $overlapped = $this->employee()->timeOff()
            ->standing()
            ->overlapping($this->firstDate(), $this->lastDate())
            ->orderBy('first_date')
            ->first();

        if ($overlapped !== null) {
            $validator->errors()->add('first_date', __('You already have Time Off from :first to :last, so these dates can\'t overlap it.', [
                'first' => $overlapped->first_date->format('j M Y'),
                'last' => $overlapped->last_date->format('j M Y'),
            ]));
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
            $validator->errors()->add('first_date', __('You have a Travel Request from :first to :last, so Time Off can\'t overlap it.', [
                'first' => $overlapped->first_date->format('j M Y'),
                'last' => $overlapped->last_date->format('j M Y'),
            ]));
        }
    }

    /** Weekend Work Entries never block, as weekends are never days of Time Off. */
    private function checkWorkedDays(Validator $validator): void
    {
        $worked = $this->employee()->workEntries()
            ->counted()
            ->onWeekdaysBetween($this->firstDate(), $this->lastDate())
            ->orderBy('work_entries.date')
            ->first();

        if ($worked !== null) {
            $validator->errors()->add('first_date', __('You have hours recorded on :date, so Time Off can\'t include that day.', [
                'date' => $worked->date->format('j M Y'),
            ]));
        }
    }

    private function checkPaidDays(Validator $validator): void
    {
        foreach ($this->employee()->paidDaysUsedBy($this->unsavedTimeOff()) as ['year' => $year, 'uses' => $needed, 'left' => $left]) {
            if ($left < 0) {
                $validator->errors()->add('is_paid', trans_choice('You have no Paid Days left in :year, since :count more is already taken than you have. Request this as Unpaid Time Off.|You have no Paid Days left in :year, since :count more are already taken than you have. Request this as Unpaid Time Off.', -$left, [
                    'year' => $year,
                ]));
            } elseif ($needed > $left) {
                $validator->errors()->add('is_paid', trans_choice('You have :count Paid Day left in :year, but this Paid Time Off takes :needed. Request the rest as Unpaid Time Off.|You have :count Paid Days left in :year, but this Paid Time Off takes :needed. Request the rest as Unpaid Time Off.', $left, [
                    'year' => $year,
                    'needed' => $needed,
                ]));
            }
        }
    }
}
