import type { Outcome } from '@/types';

/**
 * Each Outcome as people read it, from worst to best, in the order they
 * are offered.
 */
export const outcomes: Record<Outcome, string> = {
    'not-met': 'Not Met',
    'partly-met': 'Partly Met',
    met: 'Met',
    exceeded: 'Exceeded',
};
