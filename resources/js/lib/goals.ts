/**
 * The first word of a person's name, as the Performance pages call the
 * Employee in sentences ("Cara").
 */
export function firstName(name: string): string {
    return name.trim().split(/\s+/u)[0];
}

/**
 * What a Goal's Outcome field is labelled for its Manager while the
 * Performance Evaluation is Open: the Employee doesn't see it until it is
 * Completed.
 */
export function goalOutcomeLabel(employeeName: string): string {
    return `Outcome (hidden from ${firstName(employeeName)} until Completed)`;
}

/**
 * What the confirmation before deleting a Goal says it takes with it:
 * its Progress history and, when it has any, its Comments by count.
 */
export function goalDeletionWarning(commentsCount: number): string {
    const warning =
        'The Goal is deleted from this Performance Evaluation for good, with its Progress history.';

    if (commentsCount === 0) {
        return warning;
    }

    return `${warning} Its ${commentsCount} ${commentsCount === 1 ? 'Comment is' : 'Comments are'} deleted with it.`;
}
