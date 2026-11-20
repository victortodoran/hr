export type UseInitialsReturn = {
    getInitials: (fullName?: string) => string;
};

function nameParts(fullName: string): string[] {
    return fullName.trim().split(/\s+/u).filter(Boolean);
}

export function firstName(fullName: string): string {
    return nameParts(fullName)[0] ?? '';
}

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function getInitials(fullName?: string): string {
    if (!fullName) {
        return '';
    }

    const names = nameParts(fullName);

    if (names.length === 0) {
        return '';
    }

    if (names.length === 1) {
        return getInitial(names[0]).toUpperCase();
    }

    return `${getInitial(names[0])}${getInitial(names[names.length - 1])}`.toUpperCase();
}

export function useInitials(): UseInitialsReturn {
    return { getInitials };
}
