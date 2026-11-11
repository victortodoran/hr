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

/**
 * The entry in effect on a YYYY-MM-DD date: the latest one taking effect on
 * or before it, or null when none has yet. The same rule as PayHistory on
 * the server.
 */
export function payInEffectOn(
    entries: PayEntryRecord[],
    date: string,
): PayEntryRecord | null {
    return entries.reduce<PayEntryRecord | null>(
        (inEffect, entry) =>
            entry.effective_from <= date &&
            (inEffect === null ||
                entry.effective_from > inEffect.effective_from)
                ? entry
                : inEffect,
        null,
    );
}
