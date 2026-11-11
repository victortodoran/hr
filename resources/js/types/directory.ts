import type { Choice } from './employee-record';

export type DirectoryEntry = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    department: string;
    manager: Choice | null;
};

export type ChainLink = {
    id: number;
    name: string;
    department: string;
};
