/**
 * One page of a Laravel paginator, as Inertia receives it.
 */
export type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
