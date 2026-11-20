import type { PermissionChoice, Scope } from '@/types';

/** Narrowest first. */
export const scopes: { value: Scope; label: string; help: string }[] = [
    { value: 'own', label: 'Own', help: 'only themselves' },
    { value: 'reports', label: 'Reports', help: 'people who report to them' },
    { value: 'everyone', label: 'Everyone', help: 'whole company' },
];

export function groupPermissions(
    permissions: PermissionChoice[],
): { group: string; permissions: PermissionChoice[] }[] {
    const groups: { group: string; permissions: PermissionChoice[] }[] = [];

    for (const permission of permissions) {
        const last = groups.at(-1);

        if (last?.group === permission.group) {
            last.permissions.push(permission);
        } else {
            groups.push({ group: permission.group, permissions: [permission] });
        }
    }

    return groups;
}

export function countOf(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}
