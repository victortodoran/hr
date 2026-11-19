<?php

namespace App\Http\Requests\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new Performance Evaluation: the year, the current or the next, and an
 * Employee the signed-in Employee may create one for that year
 * (PerformanceEvaluationPolicy::create()).
 */
class PerformanceEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['bail', 'required', 'integer', Rule::in(PerformanceEvaluation::creatableYears())],
            'employee_id' => ['bail', 'required', 'integer', Rule::exists(Employee::class, 'id')],
        ];
    }

    /**
     * Once both fields are valid on their own, refuse an Employee who
     * already has a Performance Evaluation for the year, or whom the
     * signed-in Employee may not create one for.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = $this->employee();
            $year = $this->year();

            if ($employee->performanceEvaluations()->where('year', $year)->exists()) {
                $validator->errors()->add('employee_id', __(':name already has a :year Performance Evaluation.', ['name' => $employee->name, 'year' => $year]));

                return;
            }

            if ($this->actor()->cannot('create', [PerformanceEvaluation::class, $employee, $year])) {
                $validator->errors()->add('employee_id', __('Choose one of the people you\'re the Manager of who work here in :year.', ['year' => $year]));
            }
        }];
    }

    /**
     * The year the Performance Evaluation is for.
     */
    public function year(): int
    {
        return $this->integer('year');
    }

    /**
     * The Employee the Performance Evaluation is for.
     */
    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->integer('employee_id'));
    }

    /**
     * The Employee creating it: always the one signed in.
     */
    private function actor(): Employee
    {
        /** @var Employee */
        return $this->user();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.in' => __('Choose this year or next year.'),
            'employee_id.required' => __('Choose the Employee this Performance Evaluation is for.'),
            'employee_id.integer' => __('Choose one of the people you\'re the Manager of.'),
            'employee_id.exists' => __('Choose one of the people you\'re the Manager of.'),
        ];
    }
}
