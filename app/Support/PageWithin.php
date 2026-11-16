<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A page of a list that never lands past its end. After a bulk action from
 * a later page, such as approving every Work Entry on it, the page asked
 * for may no longer exist; the list then shows its last page instead of an
 * empty one that looks like nothing is left.
 */
final class PageWithin
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        $page = (clone $query)->paginate($perPage);

        return $page->currentPage() > $page->lastPage()
            ? $query->paginate($perPage, page: $page->lastPage())
            : $page;
    }
}
