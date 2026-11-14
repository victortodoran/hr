/**
 * A date as people read it, such as "5 Jan 2026".
 */
export function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * A period as people read it, such as "5 Jan 2026 – no end date".
 */
export function formatPeriod(start: string, end: string | null): string {
    return `${formatDate(start)} – ${end ? formatDate(end) : 'no end date'}`;
}

/**
 * Today in the viewer's time zone, as a YYYY-MM-DD date.
 */
export function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

/**
 * The month a number of months after one, both as YYYY-MM; a negative
 * number goes back.
 */
export function addMonths(month: string, months: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + months, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * A YYYY-MM month as people read it, such as "November 2026".
 */
export function formatMonth(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

/**
 * A day's short name and number, such as "Thu 12".
 */
export function formatWeekdayAndDay(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    return `${day.toLocaleDateString(undefined, { weekday: 'short' })} ${day.getDate()}`;
}

/**
 * A day's full name, such as "Thursday 12 November".
 */
export function formatLongDay(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    return `${day.toLocaleDateString(undefined, { weekday: 'long' })} ${day.getDate()} ${day.toLocaleDateString(undefined, { month: 'long' })}`;
}
