import type { StatusBadgeState } from './status';

export type PayKind = 'fixed' | 'hourly';

export type PayChangeState = Extract<
    StatusBadgeState,
    'upcoming' | 'in-effect' | 'past'
>;

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

export type PayInEffect = {
    project: string;
    is_internal: boolean;
    kind: PayKind;
    amount: string;
};

export type CompanySettings = {
    name: string;
    currency: string;
};
