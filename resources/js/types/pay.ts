/**
 * The two kinds of Pay: Fixed Pay per month or Hourly Pay.
 */
export type PayKind = 'fixed' | 'hourly';

/**
 * One entry in an Assignment's Pay history.
 */
export type PayEntryRecord = {
    id: number;
    effective_from: string;
    kind: PayKind;
    /** Already shown in the company currency, such as "€4,500.00". */
    amount: string;
    /** Whether this is the entry in effect today. */
    is_in_effect: boolean;
};

/**
 * The company's name and the currency every Pay is recorded in.
 */
export type CompanySettings = {
    name: string;
    currency: string;
};
