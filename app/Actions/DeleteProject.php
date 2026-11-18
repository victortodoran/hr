<?php

namespace App\Actions;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a Project, refusing once anyone has been assigned to it, since
 * that work is part of their record.
 */
class DeleteProject
{
    /**
     * @throws ValidationException
     */
    public function delete(Project $project): void
    {
        if ($project->assignments()->exists()) {
            throw ValidationException::withMessages([
                'project' => __(':project has Assignments, so it can\'t be deleted. Set an end date instead.', ['project' => $project->name]),
            ]);
        }

        $project->delete();
    }
}
