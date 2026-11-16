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
