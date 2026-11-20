export function formatDays(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}

export function timeOffKindLabel(kind: 'paid' | 'unpaid'): string {
    return kind === 'paid' ? 'Paid Time Off' : 'Unpaid Time Off';
}
