import type { Choice } from './employee-record';

export type ProjectRecord = {
    id: number;
    name: string;
    client: Choice | null;
    start_date: string | null;
    end_date: string | null;
    is_internal: boolean;
};

/**
 * A Project's team today, among the Assignments the viewer may see: how
 * many Employees run on it and their Capacity in all, in percent.
 */
export type TeamToday = {
    employees: number;
    capacity: number;
};
