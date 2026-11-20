export type CommandMenuResults = {
    people: {
        id: number;
        name: string;
        job_title: string | null;
        /** Opens their Employee page when true, else their Directory entry. */
        canViewEmployee: boolean;
    }[];
    projects: { id: number; name: string; client: string | null }[];
    clients: { id: number; name: string }[];
};
