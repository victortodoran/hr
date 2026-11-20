import type { TravelExpenseCategory } from '@/types';

/**
 * Each Travel Expense Category as people read it, in the order they are
 * offered.
 */
export const travelExpenseCategories: Record<TravelExpenseCategory, string> = {
    transport: 'Transport',
    accommodation: 'Accommodation',
    meals: 'Meals',
    other: 'Other',
};

/**
 * Minor units of a currency as people read them, such as "RON 1,990.00",
 * as the server formats each amount, for totals worked out in the page.
 */
export function formatMinorUnits(minorUnits: number, currency: string): string {
    const format = new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
    });
    const decimals = format.resolvedOptions().maximumFractionDigits ?? 2;

    return format.format(minorUnits / 10 ** decimals);
}
