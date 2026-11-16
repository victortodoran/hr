/**
 * Where a Travel Request stands: Pending until decided, Approved or
 * Declined once decided, and Cancelled once withdrawn before its first day.
 */
export type TravelRequestState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'cancelled';

/**
 * One of the signed-in Employee's Travel Requests, with the Project and
 * Client of its Assignment and whether they may still cancel it.
 */
export type TravelRequestRecord = {
    id: number;
    project: string;
    /** Empty for the Internal Assignment, whose Project has no Client. */
    client: string | null;
    destination: string;
    first_date: string;
    last_date: string;
    /** Every day from the first to the last date, weekends included. */
    days: number;
    purpose: string;
    state: TravelRequestState;
    /** The name of the Manager who approved or declined it, once decided. */
    decided_by: string | null;
    /** Why it was declined; only a Declined Travel Request has one. */
    decline_reason: string | null;
    can_cancel: boolean;
};

/**
 * One of the signed-in Employee's Assignments a Travel Request can be made
 * on, with the dates its days must fall within.
 */
export type TravelAssignment = {
    id: number;
    project: string;
    client: string | null;
    start_date: string;
    end_date: string | null;
};
