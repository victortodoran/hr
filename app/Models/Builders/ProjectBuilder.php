<?php

namespace App\Models\Builders;

use App\Models\Project;

/**
 * Every reusable query on Projects.
 *
 * @extends ModelBuilder<Project>
 */
class ProjectBuilder extends ModelBuilder
{
    /**
     * Limit the query to the Internal Project.
     */
    public function internal(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_internal'), true);
        });
    }

    /**
     * Limit the query to Projects other than the Internal Project.
     */
    public function notInternal(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_internal'), false);
        });
    }
}
