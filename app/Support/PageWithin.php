<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/** After a bulk action empties a later page, the list shows its last page instead of an empty one that looks like nothing is left. */
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
