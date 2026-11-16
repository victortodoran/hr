import type { PermissionChoice, Scope } from '@/types';

/** Each Scope's name and what it covers, narrowest first. */
export const scopes: { value: Scope; label: string; help: string }[] = [
    { value: 'own', label: 'Own', help: 'only themselves' },
    { value: 'reports', label: 'Reports', help: 'people who report to them' },
    { value: 'everyone', label: 'Everyone', help: 'whole company' },
];

export function scopeLabel(scope: Scope): string {
    return scopes.find((choice) => choice.value === scope)?.label ?? scope;
}

/**
 * The Permissions in their groups, both in the order the server sends them.
 */
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

/** "1 permission", "3 permissions". */
export function countOf(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}
