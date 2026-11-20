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
 * A moment as people read it, such as "5 Jan 2026, 14:30".
 */
export function formatDateTime(dateTime: string): string {
    return new Date(dateTime).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * A period as people read it, such as "5 Jan 2026 – no end date".
 */
export function formatPeriod(start: string, end: string | null): string {
    return `${formatDate(start)} – ${end ? formatDate(end) : 'no end date'}`;
}

/**
 * The days from a first to a last date as people read them, such as
 * "3 Dec 2026 – 5 Dec 2026", or one date when they are the same.
 */
export function formatDateRange(first: string, last: string): string {
    return first === last
        ? formatDate(first)
        : `${formatDate(first)} – ${formatDate(last)}`;
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
 * A YYYY-MM month's short name, such as "Nov".
 */
export function formatShortMonth(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
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

/**
 * Whether a date, as YYYY-MM-DD, is a Saturday or Sunday.
 */
export function isWeekend(date: string): boolean {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return weekday === 0 || weekday === 6;
}

/**
 * The Monday of a date's week, as a YYYY-MM-DD date.
 */
export function mondayOf(date: string): string {
    const day = new Date(`${date}T00:00:00`);
    day.setDate(day.getDate() - ((day.getDay() + 6) % 7));

    return [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * The days from a first to a last date as people read them, sharing what
 * the two have in common (rule C9): "Nov 30 – Dec 4, 2026",
 * "Nov 16 – 22, 2026", or one date when they are the same.
 */
export function formatDateSpan(first: string, last: string): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).formatRange(new Date(`${first}T00:00:00`), new Date(`${last}T00:00:00`));
}

/**
 * How many days run from a first to a last date, both counted; only
 * Mondays to Fridays when `weekdaysOnly` is set. None when the last date
 * comes before the first.
 */
export function countDays(
    first: string,
    last: string,
    weekdaysOnly = false,
): number {
    const day = new Date(`${first}T00:00:00`);
    const end = new Date(`${last}T00:00:00`);
    let days = 0;

    while (day <= end) {
        const weekday = day.getDay();

        if (!weekdaysOnly || (weekday !== 0 && weekday !== 6)) {
            days++;
        }

        day.setDate(day.getDate() + 1);
    }

    return days;
}
