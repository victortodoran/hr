<?php

namespace App\Http\Requests\Projects;

use App\Models\Client;
use App\Models\Project;
use App\Rules\AssignmentsFit;
use App\Rules\TravelRequestsFit;
use App\Rules\WorkEntriesFit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $project = $project instanceof Project ? $project : null; // @pest-mutate-ignore (the route parameter is always a Project or absent)

        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', Rule::exists(Client::class, 'id')],
            'start_date' => ['bail', 'required', 'date', AssignmentsFit::startOf($project, ':name has an Assignment starting on :date, so it can\'t start later than that.')],
            'end_date' => ['bail', 'nullable', 'date', 'after_or_equal:start_date', AssignmentsFit::endOf($project, ':name has an Assignment starting on :date, so it can\'t end earlier than that.'), WorkEntriesFit::endOf($project?->workEntries(), __(':name has Work Entries up to :date, so it can\'t end earlier than that.', ['name' => $project?->name])), TravelRequestsFit::endOf($project?->travelRequests(), ':name has a Travel Request on :project up to :date, so it can\'t end earlier than that.')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['client_id' => 'client'];
    }
}
