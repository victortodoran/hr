import type { ProjectRecord } from '@/types';

/**
 * The Client dropdown can't hold null, so "no Client" travels as this value.
 */
export const noClient = 'none';

/** A Project's fields as its form holds them. */
export type ProjectFields = {
    name: string;
    client_id: string;
    start_date: string;
    end_date: string;
};

/**
 * The form's fields as the Project stands now, or blank for a new one.
 */
export function projectFields(project?: ProjectRecord): ProjectFields {
    return {
        name: project?.name ?? '',
        client_id: project?.client ? String(project.client.id) : noClient,
        start_date: project?.start_date ?? '',
        end_date: project?.end_date ?? '',
    };
}

/**
 * The fields as the server takes them: no Client and no end date as null.
 */
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
