import type { WorkEntryListFilters } from './work-entry';

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
 * One row of an Employee's list of travel, opening the Travel Request's
 * own page.
 */
export type TravelRequestSummary = {
    id: number;
    destination: string;
    first_date: string;
    last_date: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    state: TravelRequestState;
    /** Whether it can have Travel Expenses: Approved, or Cancelled after it was Approved. */
    takes_expenses: boolean;
    /**
     * The total of its Travel Expenses entered that aren't Declined, already
     * shown in the company currency; none for a Travel Request that doesn't
     * take them.
     */
    entered_total: string | null;
    /**
     * The total of its Approved Travel Expenses, already shown in the
     * company currency; none for a Travel Request that doesn't take them.
     */
    approved_total: string | null;
    /** How many of its Travel Expenses are Declined. */
    declined_expenses: number;
};

/**
 * An Employee's Travel Requests, split as the Travel page shows them.
 * Upcoming: Pending or Approved, not ended yet, soonest first. Past: every
 * other, newest first.
 */
export type TravelHistory = {
    upcoming: TravelRequestSummary[];
    past: TravelRequestSummary[];
};

/**
 * What names a Travel Request at the top of a Travel Expense's form.
 */
export type TravelRequestName = {
    id: number;
    destination: string;
    first_date: string;
    last_date: string;
};

/**
 * One Travel Request in full, as its own page shows it, with the Project
 * and Client of its Assignment and whether the viewer may still cancel it:
 * only ever the Employee themselves.
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
export type TravelExpenseState = 'pending' | 'approved' | 'declined';

/** One Travel Expense of an Employee, with its receipt. */
export type TravelExpenseRecord = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    description: string;
    /** Already shown in the company currency, such as "RON 1,990.00". */
    amount: string;
    state: TravelExpenseState;
    /** Why it was declined; only a Declined Travel Expense has one. */
    decline_reason: string | null;
    /** Whether the viewer may change or delete it: only the Employee, while Pending or Declined. */
    can_change: boolean;
};

/** The Travel Expenses of one Travel Request, with their total. */
export type TravelExpenses = {
    items: TravelExpenseRecord[];
    /** Of those not Declined, already shown in the company currency. */
    total: string;
    can_create: boolean;
};

/** A Travel Expense as its change form is filled in. */
export type TravelExpenseTyped = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    /** As people type it, such as "1990.00". */
    amount: string;
    description: string;
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

/**
 * A Travel Request with the Pending Travel Expenses of an Employee the
 * viewer effectively manages, for them to approve or decline on the
 * Approvals page.
 */
export type TravelExpensesToApprove = {
    /** The Travel Request's ID. */
    id: number;
    employee: { id: number; name: string };
    destination: string;
    first_date: string;
    last_date: string;
    expenses: {
        id: number;
        paid_on: string;
        category: TravelExpenseCategory;
        description: string;
        /** Already shown in the company currency, such as "RON 1,990.00". */
        amount: string;
    }[];
};

/** The Travel Expenses page's filters, as on the Work Entries page. */
export type TravelExpenseListFilters = Omit<WorkEntryListFilters, 'state'> & {
    state: TravelExpenseState | null;
};

/**
 * A Travel Expense on the Travel Expenses page, across Employees, with who
 * approved it, and whether the viewer may reopen it.
 */
export type ListedTravelExpense = {
    id: number;
    paid_on: string;
    employee: { id: number; name: string };
    project: string;
    destination: string;
    category: TravelExpenseCategory;
    description: string;
    /** Already shown in the company currency, such as "RON 1,990.00". */
    amount: string;
    state: TravelExpenseState;
    /** Who approved it and when; nobody for an Employee with no Manager, approved at once. */
    approved: { by: string | null; on: string } | null;
    decline_reason: string | null;
    can_reopen: boolean;
};
