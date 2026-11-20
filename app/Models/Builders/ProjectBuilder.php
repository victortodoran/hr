<?php

namespace App\Models\Builders;

use App\Models\Concerns\SearchesByName;
use App\Models\Project;

/** @extends ModelBuilder<Project> */
class ProjectBuilder extends ModelBuilder
{
    use SearchesByName;

    public function internal(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_internal'), true);
        });
    }

    public function notInternal(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_internal'), false);
        });
    }
}
