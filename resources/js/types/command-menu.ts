/**
 * The command menu's search results (App\Pages\Search\CommandMenuResults):
 * at most five of each, only what the viewer could already open.
 */
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
