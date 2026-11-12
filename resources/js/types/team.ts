/**
 * One of the viewer's Direct Reports on the Team page, with whether the
 * viewer may open their Employee page.
 */
export type DirectReport = {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    department: string;
    can_view: boolean;
};
