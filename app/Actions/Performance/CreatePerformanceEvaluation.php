<?php

namespace App\Actions\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePerformanceEvaluation
{
    /**
     * The unique Employee and year index turns away a second one created at
     * once; the insert runs in a savepoint so that refusal doesn't spoil an
     * outer transaction.
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
