<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A Project's name, dates and optional Client, when creating or editing one.
 */
class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', Rule::exists(Client::class, 'id')],
            'start_date' => ['bail', 'required', 'date', $this->notAfterAnAssignment()],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Refuses a start date later than the start of one of the Project's
     * Assignments, since every Assignment falls within the Project's dates.
     */
    private function notAfterAnAssignment(): Closure
    {
        return function (string $attribute, mixed $startDate, Closure $fail): void {
            $project = $this->route('project');
            $firstStart = $project instanceof Project ? $project->assignments()->min('start_date') : null;

            if (is_string($firstStart) && Carbon::parse($firstStart)->isBefore(Carbon::parse($startDate))) {
                $fail(__(':project has an Assignment starting on :date, so it can\'t start later than that.', [
                    'project' => $project->name,
                    'date' => Carbon::parse($firstStart)->format('j M Y'),
                ]));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['client_id' => 'client'];
    }
}
