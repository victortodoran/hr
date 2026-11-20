import type { ListFilters } from './list-filters';

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
    /** The Project of its Assignment. */
    project: string;
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
    /** Whether the viewer may approve or decline it: its Employee's effective Manager, while Pending. */
    can_decide: boolean;
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
    /** The name of the Manager who declined it, while Declined. */
    declined_by: string | null;
    /** Whether the receipt is a picture, shown as a thumbnail; a PDF shows as a tile. */
    receipt_is_image: boolean;
    /** Whether the viewer may change or delete it: only the Employee, while Pending or Declined. */
    can_change: boolean;
    /** Whether the viewer may approve or decline it: its Employee's effective Manager, while Pending. */
    can_decide: boolean;
    /** Whether the viewer may reopen it: Approved, and their reopen Scope covers the Employee, never their own. */
    can_reopen: boolean;
};

/** The Travel Expenses of one Travel Request, with their totals. */
export type TravelExpenses = {
    items: TravelExpenseRecord[];
    /** Of those not Declined, already shown in the company currency. */
    total: string;
    /** Of those Approved, already shown in the company currency. */
    approved_total: string;
    /** Of those Pending, already shown in the company currency. */
    pending_total: string;
    /** Of those Declined, which aren't counted, already shown in the company currency. */
    declined_total: string;
    can_create: boolean;
};

/** A Travel Expense as the Edit Travel Expense sheet is filled in. */
export type TravelExpenseTyped = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    /** As people type it, such as "1990.00". */
    amount: string;
    description: string;
    state: TravelExpenseState;
    /** Why it was declined; only a Declined Travel Expense has one. */
    decline_reason: string | null;
    /** The name of the Manager who declined it, while Declined. */
    declined_by: string | null;
    /** The receipt's file name as shown, such as "Receipt.pdf". */
    receipt_name: string;
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
    /** When the Employee asked. */
    submitted_on: string | null;
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
        /** The amount in minor units, such as cents, for adding up a selection. */
        amount_minor: number;
        /** When the Employee recorded it. */
        submitted_on: string | null;
        /** Whether the receipt is a picture, previewed in the RecordSheet; a PDF is linked. */
        receipt_is_image: boolean;
    }[];
};

/** The Travel Requests page's filters, as on the Work Entries page. */
export type TravelRequestListFilters = ListFilters<TravelRequestState>;

/**
 * A Travel Request on the Travel Requests page, across Employees, with a
 * summary of its Travel Expenses.
 */
export type ListedTravelRequest = {
    id: number;
    employee: { id: number; name: string };
    project: string;
    destination: string;
    first_date: string;
    last_date: string;
    state: TravelRequestState;
    /**
     * The total of its Travel Expenses that aren't Declined, already shown
     * in the company currency, and how many are Pending; none when it has
     * no Travel Expenses.
     */
    expenses: { total: string; pending: number } | null;
};
