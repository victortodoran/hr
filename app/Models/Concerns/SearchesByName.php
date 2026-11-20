<?php

namespace App\Models\Concerns;

use App\Models\Builders\ModelBuilder;

/** @phpstan-require-extends ModelBuilder */
trait SearchesByName
{
    /** % and _ in the search match literally, not as wildcards. */
    public function whereNameContains(string $search): static
    {
        return $this->scoped(function () use ($search): void {
            $this->whereLike('name', '%'.addcslashes($search, '\\%_').'%');
        });
    }
}
