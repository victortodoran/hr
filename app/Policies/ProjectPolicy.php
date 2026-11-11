<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\Project;

/**
 * Who may see and change Projects. Each check delegates to the Gate of the
 * Permission behind it, and the Internal Project is never changed here.
 */
class ProjectPolicy
{
    /**
     * Seeing the Projects needs view projects, or manage projects, since
     * managing them means seeing them.
     */
    public function viewAny(Employee $actor): bool
    {
        return $actor->can(Permission::ViewProjects->value)
            || $actor->can(Permission::ManageProjects->value);
    }

    /**
     * Seeing a Project and its team needs the same as seeing the Projects.
     * The team itself only lists the Assignments the viewer may see.
     */
    public function view(Employee $actor, Project $project): bool
    {
        return $this->viewAny($actor);
    }

    /**
     * Creating a Project needs manage projects.
     */
    public function create(Employee $actor): bool
    {
        return $actor->can(Permission::ManageProjects->value);
    }

    /**
     * Editing a Project needs manage projects. The Internal Project can't be
     * edited: it has no dates or Client, and is named after the company.
     */
    public function update(Employee $actor, Project $project): bool
    {
        return $actor->can(Permission::ManageProjects->value) && ! $project->is_internal;
    }

    /**
     * Deleting a Project needs manage projects. The Internal Project can
     * never be deleted, since every Employee has an Assignment to it.
     */
    public function delete(Employee $actor, Project $project): bool
    {
        return $this->update($actor, $project);
    }
}
