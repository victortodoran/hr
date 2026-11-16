<?php

namespace App\Models\Builders;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The base of every model's builder. Its methods apply their constraints
 * the way Laravel applies a query scope, so they keep a scope's guarantee.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
abstract class ModelBuilder extends Builder
{
    /**
     * Apply the constraints as a query scope would: when the query already
     * has conditions, the ones added here go in brackets of their own, so an
     * earlier orWhere can't swallow them.
     *
     * @param  Closure(): void  $constrain
     */
    protected function scoped(Closure $constrain): static
    {
        $this->callScope(fn () => $constrain());

        return $this;
    }
}
