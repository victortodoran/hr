import type { PayKind } from '@/types';

/**
 * How often an amount of each kind of Pay is paid.
 */
export const payPeriods: Record<PayKind, string> = {
    fixed: 'per month',
    hourly: 'per hour',
};

/**
 * Each kind of Pay by its name (C1).
 */
export const payKindNames: Record<PayKind, string> = {
    fixed: 'Fixed Pay',
    hourly: 'Hourly Pay',
};

/**
 * An amount of Pay as people read it, such as "€4,500.00 per month".
 */
export function formatPay(pay: { amount: string; kind: PayKind }): string {
    return `${pay.amount} ${payPeriods[pay.kind]}`;
}
