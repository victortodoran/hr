export function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(dateTime: string): string {
    return new Date(dateTime).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatPeriod(start: string, end: string | null): string {
    return `${formatDate(start)} – ${end ? formatDate(end) : 'no end date'}`;
}

export function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

export function addMonths(month: string, months: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + months, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export function formatMonth(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

export function formatShortMonth(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
    });
}

export function formatWeekdayAndDay(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    return `${day.toLocaleDateString(undefined, { weekday: 'short' })} ${day.getDate()}`;
}

export function formatLongDay(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    return `${day.toLocaleDateString(undefined, { weekday: 'long' })} ${day.getDate()} ${day.toLocaleDateString(undefined, { month: 'long' })}`;
}

export function isWeekend(date: string): boolean {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return weekday === 0 || weekday === 6;
}

export function mondayOf(date: string): string {
    const day = new Date(`${date}T00:00:00`);
    day.setDate(day.getDate() - ((day.getDay() + 6) % 7));

    return [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
}

export function formatDateRange(first: string, last: string): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).formatRange(new Date(`${first}T00:00:00`), new Date(`${last}T00:00:00`));
}

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
