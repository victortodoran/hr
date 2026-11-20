import type { Period } from './period';

/** `employee` is null only when nobody has anything in the Period; `state` is null for any. */
export type ListFilters<State extends string> = {
    period: Period;
    employee: number | 'all' | null;
    project?: number | null;
    state: State | null;
};

export type EmployeeWithCount = {
    id: number;
    name: string;
    count: number;
};

export type FilterChoice = {
    value: string;
    label: string;
};

export type StateChoice<State extends string = string> = FilterChoice & {
    value: State;
};
