<?php

namespace App\Http\Requests;

use App\Models\TimeOff;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Approving Pending Time Off. Paid Time Off is checked again against the
 * Employee's Paid Days, which may have been lowered since it was requested.
 * Their Paid Days left already count it while it is Pending, so its own
 * weekdays are added back before comparing.
 */
class TimeOffApprovalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Refuse Paid Time Off whose weekdays in any year it touches no longer
     * fit that year's Paid Days left besides itself, and say how many are
     * left so the Manager can decline it instead. Days left below 0, after
     * HR lowered Paid Days, are told as none left.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $timeOff = $this->timeOff();

            if (! $timeOff->is_paid) {
                return;
            }

            foreach (range($timeOff->first_date->year, $timeOff->last_date->year) as $year) {
                $needed = $timeOff->weekdaysIn($year);
                $left = $timeOff->employee->paidDaysLeftIn($year) + $needed;

                if ($left < 0) {
                    $validator->errors()->add('approval', trans_choice(':name has no Paid Days left in :year besides this Time Off, since :count more is already taken than they have. Decline it instead, so it can be requested as Unpaid Time Off.|:name has no Paid Days left in :year besides this Time Off, since :count more are already taken than they have. Decline it instead, so it can be requested as Unpaid Time Off.', -$left, [
                        'name' => $timeOff->employee->name,
                        'year' => $year,
                    ]));
                } elseif ($needed > $left) {
                    $validator->errors()->add('approval', trans_choice(':name has :count Paid Day left in :year besides this Time Off, but it takes :needed. Decline it instead, so the rest can be requested as Unpaid Time Off.|:name has :count Paid Days left in :year besides this Time Off, but it takes :needed. Decline it instead, so the rest can be requested as Unpaid Time Off.', $left, [
                        'name' => $timeOff->employee->name,
                        'year' => $year,
                        'needed' => $needed,
                    ]));
                }
            }
        }];
    }

    /**
     * The Time Off being approved.
     */
    public function timeOff(): TimeOff
    {
        /** @var TimeOff */
        return $this->route('timeOff');
    }
}
