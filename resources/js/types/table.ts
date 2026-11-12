/**
 * One column of the shared table. `key` names the row field shown by default
 * and the `cell-<key>` slot that replaces it. Minor columns hide below the
 * small breakpoint so a phone shows only what matters.
 */
export type TableColumn = {
    key: string;
    label: string;
    minor?: boolean;
    align?: 'start' | 'end';
};
