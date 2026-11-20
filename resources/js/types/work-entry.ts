import type { ListFilters } from './list-filters';

export type WorkEntryState = 'pending' | 'approved' | 'declined';

export type WorkEntryDecline = {
    by: string;
    reason: string;
};

export type WorkEntrySignOff = {
    by: string;
    on: string;
};

export type WorkEntryRecord = {
    id: number;
    assignment_id: number;
    project: string;
    hours: number;
    description: string;
    state: WorkEntryState;
    can_edit: boolean;
    can_delete: boolean;
    declined: WorkEntryDecline | null;
};

export type WorkEntryWeek = {
    number: number;
    from: string;
    to: string;
    hours: number;
};

export type WorkEntriesTab = 'list' | 'calendar';

export type WorkEntryCalendarWeek = {
    number: number;
    hours: number;
    days: (WorkEntryDay | null)[];
};

export type WorkEntryDay = {
    date: string;
    entries: WorkEntryRecord[];
    hours: number;
    time_off: 'paid' | 'unpaid' | null;
    /** A past weekday while employed, with no Work Entries and no Time Off. */
    nothing_logged: boolean;
    /** Null when there's none or copying isn't allowed. */
    copy_from: string | null;
};

export type WorkEntryAssignmentTotal = {
    assignment_id: number;
    project: string;
    hours: number;
};

export type WorkEntryAssignment = {
    id: number;
    project: string;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
};

export type ApprovalEntry = {
    id: number;
    date: string;
    employee: { id: number; name: string };
    project: string;
    hours: number;
    description: string;
    submitted_on: string | null;
};

export type ApprovalWeek = {
    /** Its Monday. */
    starts_on: string;
    /** Its Sunday. */
    ends_on: string;
    number: number;
    hours: number;
};

export type ListedWorkEntry = Omit<ApprovalEntry, 'submitted_on'> & {
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    declined: WorkEntryDecline | null;
    can_reopen: boolean;
};

export type ApprovalsTab =
    | 'work-entries'
    | 'time-off'
    | 'travel-requests'
    | 'travel-expenses';

export type ApprovalsWaiting = {
    workEntries: number;
    timeOff: number;
    travelRequests: number;
    travelExpenses: number;
};

export type ApprovalFilters = {
    employee: number | null;
    project: number | null;
};

export type ChoiceToApprove = {
    id: number;
    name: string;
    pending: number;
};

export type WorkEntryListFilters = ListFilters<WorkEntryState>;
