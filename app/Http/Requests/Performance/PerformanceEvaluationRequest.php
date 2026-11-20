<?php

namespace App\Http\Requests\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PerformanceEvaluationRequest extends FormRequest
{
    /** The Employee asked for, once looked up. */
    private ?Employee $employee = null;

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
     * Whether someone the actor doesn't manage already has one stays hidden.
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

            if ($this->actor()->can('create', [PerformanceEvaluation::class, $employee, $year])) {
                return;
            }

            $managesThem = Employee::query()->effectivelyManagedBy($this->actor())->whereKey($employee->id)->exists();

            if ($managesThem && $employee->performanceEvaluations()->where('year', $year)->exists()) {
                $validator->errors()->add('employee_id', __(':name already has a :year Performance Evaluation.', ['name' => $employee->name, 'year' => $year]));

                return;
            }

            $validator->errors()->add('employee_id', __('Choose one of the people you\'re the Manager of who work here in :year.', ['year' => $year]));
        }];
    }

    public function year(): int
    {
        return $this->integer('year');
    }

    public function employee(): Employee
    {
        return $this->employee ??= Employee::query()->findOrFail($this->integer('employee_id'));
    }

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
