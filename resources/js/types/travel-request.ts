import type { ListFilters } from './list-filters';

export type TravelRequestState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'cancelled';

export type TravelRequestSummary = {
    id: number;
    destination: string;
    first_date: string;
    last_date: string;
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    state: TravelRequestState;
    takes_expenses: boolean;
    /** Already shown in the company currency; none for a Travel Request that doesn't take them. */
    entered_total: string | null;
    /** Already shown in the company currency; none for a Travel Request that doesn't take them. */
    approved_total: string | null;
    declined_expenses: number;
};

/** Upcoming: Pending or Approved, not ended yet, soonest first. Past: every other, newest first. */
export type TravelHistory = {
    upcoming: TravelRequestSummary[];
    past: TravelRequestSummary[];
};

export type TravelRequestRecord = {
    id: number;
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    destination: string;
    first_date: string;
    last_date: string;
    days: number;
    purpose: string;
    state: TravelRequestState;
    decided_by: string | null;
    decline_reason: string | null;
    can_cancel: boolean;
    can_decide: boolean;
    expenses: TravelExpenses | null;
};

export type TravelExpenseCategory =
    | 'transport'
    | 'accommodation'
    | 'meals'
    | 'other';

export type TravelExpenseState = 'pending' | 'approved' | 'declined';

export type TravelExpenseRecord = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    description: string;
    /** Already shown in the company currency, such as "RON 1,990.00". */
    amount: string;
    state: TravelExpenseState;
    decline_reason: string | null;
    declined_by: string | null;
    receipt_is_image: boolean;
    can_change: boolean;
    can_decide: boolean;
    can_reopen: boolean;
};

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

export type TravelExpenseTyped = {
    id: number;
    paid_on: string;
    category: TravelExpenseCategory;
    /** As people type it, such as "1990.00". */
    amount: string;
    description: string;
    state: TravelExpenseState;
    decline_reason: string | null;
    declined_by: string | null;
    receipt_name: string;
};

export type TravelCurrency = {
    code: string;
    /** How many decimals an amount has: 2 for the euro, 0 for the yen. */
    decimals: number;
};

export type TravelAssignment = {
    id: number;
    project: string;
    client: string | null;
    start_date: string;
    end_date: string | null;
};

export type TravelRequestToApprove = {
    id: number;
    employee: { id: number; name: string };
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    destination: string;
    first_date: string;
    last_date: string;
    days: number;
    purpose: string;
    submitted_on: string | null;
};

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
        submitted_on: string | null;
        receipt_is_image: boolean;
    }[];
};

export type TravelRequestListFilters = ListFilters<TravelRequestState>;

export type ListedTravelRequest = {
    id: number;
    employee: { id: number; name: string };
    project: string;
    destination: string;
    first_date: string;
    last_date: string;
    state: TravelRequestState;
    /** Already shown in the company currency, with how many are Pending; none without Travel Expenses. */
    expenses: { total: string; pending: number } | null;
};
