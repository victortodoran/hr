import type { WorkEntryDay } from '@/types';

/**
 * Hours as people read them, such as "7.5 h".
 */
export function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

/**
 * Whether hours can be added to a day: one up to today, for a viewer who
 * can record the Employee's hours.
 */
export function takesEntries(
    day: WorkEntryDay,
    canManage: boolean,
    today: string,
): boolean {
    return canManage && day.date <= today;
}
