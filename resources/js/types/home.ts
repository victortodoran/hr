export type HoursThisMonth = {
    /** The month, as YYYY-MM. */
    month: string;
    hours: number;
    pendingHours: number;
};

export type MonthOfHours = {
    /** The month, as YYYY-MM. */
    month: string;
    hours: number;
    pendingHours: number;
};

export type HoursThisYear = {
    year: number;
    hours: number;
    pendingHours: number;
    months: MonthOfHours[];
};

export type DeclinedWorkEntryItem = {
    kind: 'work-entry';
    id: number;
    date: string;
    project: string;
    hours: number;
    decline_reason: string;
};

export type DeclinedTravelRequestItem = {
    kind: 'travel-request';
    id: number;
    destination: string;
    first_date: string;
    last_date: string;
    decline_reason: string;
};

export type DeclinedTravelExpenseItem = {
    kind: 'travel-expense';
    id: number;
    travel_request_id: number;
    destination: string;
    description: string;
    decline_reason: string;
};

export type DeclinedTimeOffItem = {
    kind: 'time-off';
    id: number;
    first_date: string;
    last_date: string;
    decline_reason: string;
};

export type NeedsAttentionItem =
    | DeclinedWorkEntryItem
    | DeclinedTravelRequestItem
    | DeclinedTravelExpenseItem
    | DeclinedTimeOffItem;

export type NeedsAttention = {
    items: NeedsAttentionItem[];
    more: number;
};
