<?php

namespace App\Models\Concerns;

use App\Models\Builders\ModelBuilder;

/**
 * The search by part of a name that the list pages and the command menu
 * share, for the builders of models with a name: Employees, Projects and
 * Clients.
 *
 * @phpstan-require-extends ModelBuilder
 */
trait SearchesByName
{
    /**
     * Limit the query to the rows whose name holds the search, matched
     * literally: % and _ in it are no wildcards. An empty search keeps them
     * all.
     */
    public function whereNameContains(string $search): static
    {
        return $this->scoped(function () use ($search): void {
            $this->whereLike('name', '%'.addcslashes($search, '\\%_').'%');
        });
    }
}
