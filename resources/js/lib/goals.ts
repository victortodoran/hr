/**
 * What the confirmation before removing a Goal says it takes with it:
 * its Progress history and, when it has any, its Comments by count.
 */
export function goalRemovalWarning(commentsCount: number): string {
    const warning =
        'The Goal is removed from this Performance Evaluation for good, with its Progress history.';

    if (commentsCount === 0) {
        return warning;
    }

    return `${warning} Its ${commentsCount} ${commentsCount === 1 ? 'Comment is' : 'Comments are'} removed with it.`;
}
