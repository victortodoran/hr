<?php

namespace App\Actions\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an Employee's Performance Evaluation for a year, Open and with
 * no Goals yet.
 */
class CreatePerformanceEvaluation
{
    /**
     * Create it, or refuse with a validation error on the Employee when one
     * for the year was created since the form request checked: the unique
     * Employee and year index turns the second of two at once away. The
     * insert runs in its own transaction, a savepoint inside another, so
     * the refused insert doesn't spoil the rest of an outer one.
     *
     * @throws ValidationException
     */
    public function create(Employee $employee, int $year): PerformanceEvaluation
    {
        try {
            return DB::transaction(fn (): PerformanceEvaluation => $employee->performanceEvaluations()->create(['year' => $year]));
        } catch (UniqueConstraintViolationException) { // @pest-mutate-ignore (only reached when another request creates the same one between the check and this insert)
            throw ValidationException::withMessages([
                'employee_id' => __(':name already has a :year Performance Evaluation.', ['name' => $employee->name, 'year' => $year]),
            ]);
        }
    }
}
