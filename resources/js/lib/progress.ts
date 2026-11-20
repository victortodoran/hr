import { statusLabels } from '@/lib/statusMeta';
import type { Progress } from '@/types';

export const progresses: Record<Progress, string> = {
    'not-started': statusLabels['not-started'],
    'on-track': statusLabels['on-track'],
    'at-risk': statusLabels['at-risk'],
    done: statusLabels.done,
};
