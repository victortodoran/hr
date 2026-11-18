import type { WorkEntryPeriod } from './work-entry';

/**
 * A list's filters across Employees, as the server resolved them, on the
 * Work Entries and Travel Requests pages: the Period; the Employee shown,
 * "all" for any Employee, null only when nobody has anything in the
 * Period; a Project and a state, null for any.
 */
export type ListFilters<State extends string> = {
    period: WorkEntryPeriod;
    employee: number | 'all' | null;
    project: number | null;
    state: State | null;
};

/** An Employee with something listed in the Period shown, and how many. */
export type EmployeeWithCount = {
    id: number;
    name: string;
    count: number;
};

/** A state a list can be filtered by, as people read it. */
export type StateChoice<State extends string = string> = {
    value: State;
    label: string;
};
