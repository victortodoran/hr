/**
 * Where a Work Entry stands: only a Pending one can be changed.
 */
export type WorkEntryState = 'pending' | 'approved' | 'locked';

/**
 * Who approved or locked a Work Entry, and on which day.
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
};

/**
 * The days the Work Entries week page shows: a week from Monday to Sunday,
 * a calendar month, or a custom period, from and to as YYYY-MM-DD.
 */
export type WorkEntryPeriod = {
    mode: 'week' | 'month' | 'custom';
    from: string;
    to: string;
    /** The URL query asking for the Period, kept when saving or deleting. */
    query: Record<string, string>;
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
 * One day on the Calendar: its hours on each Project in each state, and its
 * Time Off or nothing logged, as the List has them.
 */
export type WorkEntryCalendarDay = {
    date: string;
    hours: number;
    projects: { project: string; state: WorkEntryState; hours: number }[];
    time_off: 'paid' | 'unpaid' | null;
    nothing_logged: boolean;
};

/**
 * One ISO week on the Calendar: its number, its hours, and its seven days
 * from Monday to Sunday, null for a day of another month.
 */
export type WorkEntryCalendarWeek = {
    number: number;
    hours: number;
    days: (WorkEntryCalendarDay | null)[];
};

/**
 * One day of the Period, with its Work Entries and their total hours.
 */
export type WorkEntryDay = {
    date: string;
    entries: WorkEntryRecord[];
    hours: number;
    /** Whether Pending or Approved Time Off covers the weekday, and which kind; null when none does. */
    time_off: 'paid' | 'unpaid' | null;
    /** A past weekday while employed, with no Work Entries and no Time Off. */
    nothing_logged: boolean;
};

/**
 * The total hours on one Assignment in the week.
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
 * and whether the viewer may reopen or lock it.
 */
export type ListedWorkEntry = ApprovalEntry & {
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    locked: WorkEntrySignOff | null;
    can_reopen: boolean;
    can_lock: boolean;
};

/** The tabs of the Approvals page. */
export type ApprovalsTab = 'work-entries' | 'time-off';

/** How many Pending Work Entries and Time Off wait for the viewer's decision. */
export type ApprovalsWaiting = {
    workEntries: number;
    timeOff: number;
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

/**
 * The Work Entries page's filters, as the server resolved them: the month
 * as YYYY-MM; the Employee shown, "all" for any Employee, null only when
 * nobody has Work Entries that month; a Project and a state, null for any.
 */
export type WorkEntryListFilters = {
    month: string;
    employee: number | 'all' | null;
    project: number | null;
    state: WorkEntryState | null;
};

/** An Employee with Work Entries in the month shown, and how many. */
export type EmployeeWithWorkEntries = {
    id: number;
    name: string;
    count: number;
};

/**
 * What locking the selected Work Entries would do: the Approved ones to be
 * locked, by Project and Employee, and the Pending ones matching the same
 * filters that won't be.
 */
export type LockSummary = {
    locking: { count: number; hours: number };
    breakdown: { project: string; employee: string; hours: number }[];
    pending: { count: number; hours: number };
};
