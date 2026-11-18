import type { ListFilters } from './list-filters';

/**
 * Where a Work Entry stands: only a Pending or Declined one can be changed,
 * and a Declined one's hours count nowhere.
 */
export type WorkEntryState = 'pending' | 'approved' | 'declined';

/**
 * Who declined a Work Entry, and why.
 */
export type WorkEntryDecline = {
    by: string;
    reason: string;
};

/**
 * Who approved a Work Entry, and on which day.
 */
export type WorkEntrySignOff = {
    by: string;
    on: string;
};

/**
 * How long an Employee worked on one Assignment on one date, and what
 * they did.
 */
export type WorkEntryRecord = {
    id: number;
    assignment_id: number;
    project: string;
    hours: number;
    description: string;
    state: WorkEntryState;
    /** Whether the viewer may change it (and so delete it too). */
    can_edit: boolean;
    /** Whether the viewer may delete it. */
    can_delete: boolean;
    /** Who declined it and why, while it is Declined. */
    declined: WorkEntryDecline | null;
};

/**
 * One ISO week of the Period: its number, the first and last of its days
 * within the Period, and their hours.
 */
export type WorkEntryWeek = {
    number: number;
    from: string;
    to: string;
    hours: number;
};

/** The tabs of the Work Entries week page. */
export type WorkEntriesTab = 'list' | 'calendar';

/**
 * One ISO week on the Calendar: its number, its hours, and its seven days
 * from Monday to Sunday, null for a day of another month.
 */
export type WorkEntryCalendarWeek = {
    number: number;
    hours: number;
    days: (WorkEntryDay | null)[];
};

/**
 * One day of the Period, with its Work Entries and the hours of those that
 * aren't Declined.
 */
export type WorkEntryDay = {
    date: string;
    entries: WorkEntryRecord[];
    hours: number;
    /** Whether Pending or Approved Time Off covers the weekday, and which kind; null when none does. */
    time_off: 'paid' | 'unpaid' | null;
    /** A past weekday while employed, with no Work Entries and no Time Off. */
    nothing_logged: boolean;
    /** The day copying Work Entries onto this one would copy from; null when there's none or copying isn't allowed. */
    copy_from: string | null;
};

/**
 * The total hours on one Assignment in the Period.
 */
export type WorkEntryAssignmentTotal = {
    assignment_id: number;
    project: string;
    hours: number;
};

/**
 * An Assignment hours can be recorded on, with the dates it runs.
 */
export type WorkEntryAssignment = {
    id: number;
    project: string;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
};

/**
 * A Pending Work Entry waiting in the Approvals inbox, with whose it is.
 */
export type ApprovalEntry = {
    id: number;
    date: string;
    employee: { id: number; name: string };
    project: string;
    hours: number;
    description: string;
};

/**
 * A Work Entry on the Work Entries page, with whose it is, who signed it off
 * or declined it, and whether the viewer may reopen it.
 */
export type ListedWorkEntry = ApprovalEntry & {
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    declined: WorkEntryDecline | null;
    can_reopen: boolean;
};

/** The tabs of the Approvals page. */
export type ApprovalsTab =
    | 'work-entries'
    | 'time-off'
    | 'travel-requests'
    | 'travel-expenses';

/**
 * How many Pending Work Entries, Time Off, Travel Requests and Travel
 * Expenses wait for the viewer's decision.
 */
export type ApprovalsWaiting = {
    workEntries: number;
    timeOff: number;
    travelRequests: number;
    travelExpenses: number;
};

/**
 * The Approvals Work Entries tab's filters: the Employee and Project shown,
 * null only when nothing waits at all.
 */
export type ApprovalFilters = {
    employee: number | null;
    project: number | null;
};

/** An Employee or Project with Pending Work Entries for the viewer, and how many. */
export type ChoiceToApprove = {
    id: number;
    name: string;
    pending: number;
};

/** The Work Entries page's filters, as the server resolved them. */
export type WorkEntryListFilters = ListFilters<WorkEntryState>;
