import type { Choice } from './employee-record';
import type { PayEntryRecord, PayInEffect } from './pay';

export type AssignmentRecord = {
    id: number;
    project: Choice & { client: string | null; is_internal: boolean };
    /** Only empty on the Internal Assignment, before an internal Job Title is set. */
    job_title: string | null;
    start_date: string;
    end_date: string | null;
    /** A whole percentage of the Employee's time, from 0 to 100. */
    capacity: number;
    is_active: boolean;
    has_ended: boolean;
    can_delete: boolean;
    /** The Pay history, newest first; only sent to viewers who may see it. */
    pay?: PayEntryRecord[];
};

export type AssignmentStats = {
    capacity_assigned: number;
    active: number;
    ended: number;
    pay_in_effect?: PayInEffect[];
    more_pay_in_effect?: number;
};

export type OpenProject = Choice & {
    client: string | null;
    start_date: string;
    end_date: string | null;
};

export type ClientProject = Choice & {
    start_date: string;
    end_date: string | null;
    team: TeamAssignment[];
};

export type TeamAssignment = {
    id: number;
    employee: Choice;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
    capacity: number;
};
