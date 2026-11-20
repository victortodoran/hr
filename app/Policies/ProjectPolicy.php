<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\Project;

class ProjectPolicy
{
    /** Managing Projects means seeing them. */
    public function viewAny(Employee $actor): bool
    {
        return $actor->can(Permission::ViewProjects->value)
            || $actor->can(Permission::ManageProjects->value);
    }

    public function view(Employee $actor, Project $project): bool
    {
        return $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return $actor->can(Permission::ManageProjects->value);
    }

    /**
     * The Internal Project has no dates or Client and is named after the
     * company, so it can't be edited.
     */
    public function update(Employee $actor, Project $project): bool
    {
        return $actor->can(Permission::ManageProjects->value) && ! $project->is_internal;
    }

    /** The Internal Project can never be deleted, since every Employee has an Assignment to it. */
    public function delete(Employee $actor, Project $project): bool
    {
        return $this->update($actor, $project);
    }
}
