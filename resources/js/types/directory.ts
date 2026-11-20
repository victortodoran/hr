import type { Choice, EmploymentStatus } from './employee-record';

export type DirectoryEntry = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    department: string;
    manager: Choice | null;
};

export type ChainMember = {
    id: number;
    name: string;
    job_title: string | null;
    department: string | null;
    status: EmploymentStatus;
    link: 'employee' | 'directory' | null;
};

export type ReportingChainView = {
    above: ChainMember[];
    employee: ChainMember;
    below: ChainMember[];
};

export type DirectoryTab = 'everyone' | 'reports';
