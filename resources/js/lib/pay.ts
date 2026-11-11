import type { PayEntryRecord, PayKind } from '@/types';

/**
 * How often an amount of each kind of Pay is paid.
 */
export const payPeriods: Record<PayKind, string> = {
    fixed: 'per month',
    hourly: 'per hour',
};

/**
 * An entry's amount as people read it, such as "€4,500.00 per month".
 */
export function formatPay(entry: PayEntryRecord): string {
    return `${entry.amount} ${payPeriods[entry.kind]}`;
}
