import { formatDateRange, formatMonth } from '@/lib/dates';
import type { Period, WorkEntryDay } from '@/types';

export function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

export function takesEntries(
    day: WorkEntryDay,
    canManage: boolean,
    today: string,
): boolean {
    return canManage && day.date <= today;
}

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
