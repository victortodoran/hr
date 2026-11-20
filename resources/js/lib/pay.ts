import type { PayKind } from '@/types';

export const payPeriods: Record<PayKind, string> = {
    fixed: 'per month',
    hourly: 'per hour',
};

export const payKindNames: Record<PayKind, string> = {
    fixed: 'Fixed Pay',
    hourly: 'Hourly Pay',
};

export function formatPay(pay: { amount: string; kind: PayKind }): string {
    return `${pay.amount} ${payPeriods[pay.kind]}`;
}
