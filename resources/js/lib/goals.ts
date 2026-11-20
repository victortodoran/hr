import { firstName } from '@/composables/useInitials';

export function goalOutcomeLabel(employeeName: string): string {
    return `Outcome (hidden from ${firstName(employeeName)} until Completed)`;
}

export function goalDeletionWarning(commentsCount: number): string {
    const warning =
        'The Goal is deleted from this Performance Evaluation for good, with its Progress history.';

    if (commentsCount === 0) {
        return warning;
    }

    return `${warning} Its ${commentsCount} ${commentsCount === 1 ? 'Comment is' : 'Comments are'} deleted with it.`;
}
