import type { Choice } from './employee-record';

export type ProjectRecord = {
    id: number;
    name: string;
    client: Choice | null;
    start_date: string | null;
    end_date: string | null;
    is_internal: boolean;
};
