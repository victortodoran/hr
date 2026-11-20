/**
 * The two kinds of Pay: Fixed Pay per month or Hourly Pay.
 */
export type PayKind = 'fixed' | 'hourly';

/**
 * Where a Pay change stands today: not taken effect yet, the one in effect,
 * or replaced by a later one.
 */
export type PayChangeState = 'upcoming' | 'in_effect' | 'past';

/**
 * One Pay change in an Assignment's Pay history.
 */
export type PayEntryRecord = {
    id: number;
    effective_from: string;
    /** Its last day: the day before the next change, or the Assignment's end date; null when open-ended. */
    until: string | null;
    kind: PayKind;
    /** Already shown in the company currency, such as "€4,500.00". */
    amount: string;
    state: PayChangeState;
};

/**
 * The Pay in effect today on one of an Employee's active Assignments.
 */
export type PayInEffect = {
    project: string;
    is_internal: boolean;
    kind: PayKind;
    amount: string;
};

/**
 * The company's name and the currency every Pay is recorded in.
 */
export type CompanySettings = {
    name: string;
    currency: string;
};
