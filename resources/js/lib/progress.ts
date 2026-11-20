import { statusLabels } from '@/lib/statusMeta';
import type { Progress } from '@/types';

/**
 * Each Progress as people read it, in the order they are offered: the
 * status badge's own labels.
 */
export const progresses: Record<Progress, string> = {
    'not-started': statusLabels['not-started'],
    'on-track': statusLabels['on-track'],
    'at-risk': statusLabels['at-risk'],
    done: statusLabels.done,
};
