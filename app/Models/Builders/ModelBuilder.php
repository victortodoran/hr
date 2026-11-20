<?php

namespace App\Models\Builders;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
abstract class ModelBuilder extends Builder
{
    /**
     * When the query already has conditions, the ones added here go in brackets of their own, so an earlier orWhere can't swallow them.
     *
     * @param  Closure(): void  $constrain
     */
    protected function scoped(Closure $constrain): static
    {
        $this->callScope(fn () => $constrain());

        return $this;
    }
}
