import type { Outcome } from '@/types';

/** Worst to best, in the order they are offered. */
export const outcomes: Record<Outcome, string> = {
    'not-met': 'Not Met',
    'partly-met': 'Partly Met',
    met: 'Met',
    exceeded: 'Exceeded',
};
