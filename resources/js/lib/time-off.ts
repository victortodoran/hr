/**
 * Days of Time Off or travel as people read them, such as "1 day" or "3 days".
 */
export function formatDays(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}

/**
 * The kind of Time Off as people read it: "Paid Time Off" or "Unpaid Time Off".
 */
export function timeOffKindLabel(kind: 'paid' | 'unpaid'): string {
    return kind === 'paid' ? 'Paid Time Off' : 'Unpaid Time Off';
}
