import type { FormDataConvertible } from '@inertiajs/core';

/**
 * The Select can't hold an empty value, so "No Manager" travels as this
 * value until the form is sent.
 */
export const noManager = 'none';

/**
 * Sends "No Manager" as an empty Manager, which the server reads as none.
 */
export function withNoManagerAsEmpty(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return data.manager_id === noManager ? { ...data, manager_id: null } : data;
}
