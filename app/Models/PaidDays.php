<?php

namespace App\Models;

use Database\Factories\PaidDaysFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How many days of Paid Time Off an Employee gets in one calendar year, as
 * in "Cara has 20 Paid Days in 2026". Years without a row of their own use
 * the most recent earlier year's number.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property int $days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
#[Fillable(['year', 'days'])] // @pest-mutate-ignore (class attributes never show as covered)
class PaidDays extends Model
{
    /** @use HasFactory<PaidDaysFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
