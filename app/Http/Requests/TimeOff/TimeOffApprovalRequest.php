<?php

namespace App\Http\Requests\TimeOff;

use App\Models\TimeOff;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Paid Days may have been lowered since the request, and Paid Days left already count this Pending Time Off, so its own weekdays are added back.
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

    public function timeOff(): TimeOff
    {
        /** @var TimeOff */
        return $this->route('timeOff');
    }
}
