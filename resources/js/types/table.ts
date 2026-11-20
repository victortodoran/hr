export type TableColumn = {
    key: string;
    label: string;
    minor?: boolean;
    align?: 'start' | 'end';
    /** Where the column shows when rows stack on a phone (P4); without one, only in the RecordSheet. */
    phone?: 'primary' | 'secondary' | 'end';
};
