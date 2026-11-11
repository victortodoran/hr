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
