import type { ProjectRecord } from '@/types';

/**
 * The Client dropdown can't hold null, so "no Client" travels as this value.
 */
export const noClient = 'none';

export type ProjectFields = {
    name: string;
    client_id: string;
    start_date: string;
    end_date: string;
};

export function projectFields(project?: ProjectRecord): ProjectFields {
    return {
        name: project?.name ?? '',
        client_id: project?.client ? String(project.client.id) : noClient,
        start_date: project?.start_date ?? '',
        end_date: project?.end_date ?? '',
    };
}

export function projectPayload({
    client_id,
    end_date,
    ...fields
}: ProjectFields) {
    return {
        ...fields,
        client_id: client_id === noClient ? null : Number(client_id),
        end_date: end_date === '' ? null : end_date,
    };
}

/** A Project anyone has been assigned to stays, since that work is part of their record (X3). */
export const deleteProjectDescription =
    "This can't be undone. A Project anyone has been assigned to can't be deleted; set an end date instead.";

export function projectCount(count: number): string {
    return count === 1 ? '1 Project' : `${count} Projects`;
}
