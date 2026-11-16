/**
 * Where a Travel Request stands: Pending until decided, Approved or
 * Declined once decided, and Cancelled once withdrawn before its first day.
 */
export type TravelRequestState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'cancelled';

/**
 * One of the signed-in Employee's Travel Requests, with the Project and
 * Client of its Assignment and whether they may still cancel it.
 */
export type TravelRequestRecord = {
    id: number;
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    destination: string;
    first_date: string;
    last_date: string;
    /** Every day from the first to the last date, weekends included. */
    days: number;
    purpose: string;
    state: TravelRequestState;
    /** The name of the Manager who approved or declined it, once decided. */
    decided_by: string | null;
    /** Why it was declined; only a Declined Travel Request has one. */
    decline_reason: string | null;
    can_cancel: boolean;
    /** Only for a Travel Request that takes Travel Expenses: Approved, or Approved before it was Cancelled. */
    expenses: TravelExpenses | null;
};

/** What a Travel Expense paid for, fixed by the HR system. */
export type TravelExpenseCategory =
    | 'transport'
    | 'accommodation'
    | 'meals'
    | 'other';

/** Where a Travel Expense stands, like a Work Entry. */
export type TravelExpenseState = 'pending' | 'approved' | 'declined' | 'locked';

/** One Travel Expense of the signed-in Employee, with its receipt. */
export type TravelExpenseRecord = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    description: string;
    /** Already shown in the company currency, such as "RON 1,990.00". */
    amount: string;
    /** As people type it, such as "1990.00", to fill the change form. */
    amount_typed: string;
    state: TravelExpenseState;
    /** Why it was declined; only a Declined Travel Expense has one. */
    decline_reason: string | null;
    /** Whether it can still be changed or deleted: Pending or Declined. */
    can_change: boolean;
};

/** The Travel Expenses of one Travel Request, with their total. */
export type TravelExpenses = {
    items: TravelExpenseRecord[];
    /** Already shown in the company currency. */
    total: string;
    can_create: boolean;
};

/** The company currency every Travel Expense is recorded in. */
export type TravelCurrency = {
    code: string;
    /** How many decimals an amount has: 2 for the euro, 0 for the yen. */
    decimals: number;
};

/**
 * One of the signed-in Employee's Assignments a Travel Request can be made
 * on, with the dates its days must fall within.
 */
export type TravelAssignment = {
    id: number;
    project: string;
    client: string | null;
    start_date: string;
    end_date: string | null;
};

/**
 * A Pending Travel Request of an Employee the viewer effectively manages,
 * for them to approve or decline on the Approvals page.
 */
export type TravelRequestToApprove = {
    id: number;
    employee: { id: number; name: string };
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    destination: string;
    first_date: string;
    last_date: string;
    /** Every day from the first to the last date, weekends included. */
    days: number;
    purpose: string;
};
