import type { EmployeeRecord } from '@/types';

/**
 * The Select can't hold an empty value, so "No Manager" travels as this
 * value until the form is sent.
 */
export const noManager = 'none';

/** An Employee's record as the Add and Edit Employee forms hold it. */
export type EmployeeFormFields = {
    name: string;
    email: string;
    department_id: string;
    /** A Manager's ID, `noManager`, or '' while none is chosen. */
    manager_id: string;
    start_date: string;
    leaving_date: string;
};

/**
 * The form's fields as the Employee's record stands now, or blank for a
 * new Employee.
 */
export function employeeFields(employee?: EmployeeRecord): EmployeeFormFields {
    return {
        name: employee?.name ?? '',
        email: employee?.email ?? '',
        department_id: employee ? String(employee.department_id) : '',
        manager_id: employee
            ? employee.manager_id === null
                ? noManager
                : String(employee.manager_id)
            : '',
        start_date: employee?.start_date ?? '',
        leaving_date: employee?.leaving_date ?? '',
    };
}

/**
 * The fields as the server takes them: "No Manager" and no Leaving Date as
 * null.
 */
export function employeePayload<Fields extends EmployeeFormFields>({
    manager_id,
    leaving_date,
    ...fields
}: Fields) {
    return {
        ...fields,
        manager_id: manager_id === noManager ? null : manager_id,
        leaving_date: leaving_date === '' ? null : leaving_date,
    };
}
