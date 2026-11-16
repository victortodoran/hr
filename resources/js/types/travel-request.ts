import type { WorkEntryListFilters, WorkEntrySignOff } from './work-entry';

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
 * One of an Employee's Travel Requests, with the Project and Client of its
 * Assignment and whether the viewer may still cancel it: only the Employee
 * themselves on the Travel page, never on the Employee page.
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

/** One Travel Expense of an Employee, with its receipt. */
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
    /** Of those not Declined, already shown in the company currency. */
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
 * approved and locked it, and whether the viewer may reopen or lock it.
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
    locked: WorkEntrySignOff | null;
    decline_reason: string | null;
    can_reopen: boolean;
    can_lock: boolean;
};
