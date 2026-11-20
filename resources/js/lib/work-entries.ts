import { formatDateRange, formatMonth } from '@/lib/dates';
import type { Period, WorkEntryDay } from '@/types';

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

/**
 * What a Period's hours cover, such as "in November 2026", "in Week 46"
 * (given the week's ISO number) or a custom range's dates.
 */
export function describePeriod(period: Period, weekNumber?: number): string {
    const { mode, from, to } = period;

    if (mode === 'month') {
        return `in ${formatMonth(from.slice(0, 7))}`;
    }

    if (mode === 'week') {
        return `in Week ${weekNumber}`;
    }

    return formatDateRange(from, to);
}
