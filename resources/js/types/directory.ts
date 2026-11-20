import type { Choice, EmploymentStatus } from './employee-record';

export type DirectoryEntry = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    department: string;
    manager: Choice | null;
};

/**
 * One Employee in a Reporting Chain, with where the viewer may follow their
 * name: their Employee page, their Directory page, or nowhere.
 */
export type ChainMember = {
    id: number;
    name: string;
    job_title: string | null;
    department: string | null;
    status: EmploymentStatus;
    link: 'employee' | 'directory' | null;
};

/**
 * An Employee's Reporting Chain: everyone above them from the highest, who
 * has no Manager, down, the Employee, and the Employees they are the Manager of.
 */
export type ReportingChainView = {
    above: ChainMember[];
    employee: ChainMember;
    below: ChainMember[];
};

/** The tabs of the Directory page. */
export type DirectoryTab = 'everyone' | 'reports';
