import type { ListFilters } from './list-filters';

export type TimeOffState = 'pending' | 'approved' | 'declined' | 'cancelled';

export type TimeOffDetails = {
    id: number;
    first_date: string;
    last_date: string;
    days: number;
    is_paid: boolean;
    state: TimeOffState;
    decided_by: string | null;
    decline_reason: string | null;
};

export type TimeOffRecord = TimeOffDetails & {
    can_cancel: boolean;
};

export type PaidDaysLeftInYear = {
    year: number;
    days: number;
};

export type EmployeeTimeOff = {
    paid_days: number;
    paid_days_left: PaidDaysLeftInYear[];
    requests: TimeOffDetails[];
};

export type TimeOffToApprove = {
    id: number;
    employee: { id: number; name: string };
    first_date: string;
    last_date: string;
    days: number;
    is_paid: boolean;
    submitted_on: string | null;
    /** Paid Days left per year, already counting this Time Off when Paid. */
    paid_days_left: PaidDaysLeftInYear[];
};

export type TimeOffPreview = {
    weekdays: number;
    years: { year: number; uses: number; left_after: number }[];
};

export type TimeOffListFilters = Omit<ListFilters<TimeOffState>, 'project'>;

export type ListedTimeOff = {
    id: number;
    employee: { id: number; name: string };
    first_date: string;
    last_date: string;
    days: number;
    is_paid: boolean;
    state: TimeOffState;
};
