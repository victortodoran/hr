/**
 * Days of Time Off as people read them, such as "1 day" or "3 days".
 */
export function formatDays(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}
