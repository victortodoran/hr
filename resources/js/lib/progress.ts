import type { Progress } from '@/types';

/**
 * Each Progress as people read it, in the order they are offered.
 */
export const progresses: Record<Progress, string> = {
    'not-started': 'Not Started',
    'on-track': 'On Track',
    'at-risk': 'At Risk',
    done: 'Done',
};
