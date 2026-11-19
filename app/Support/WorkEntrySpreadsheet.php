<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\WorkEntryBuilder;
use App\Models\WorkEntry;
use OpenSpout\Common\Entity\Cell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Work Entries as a file to download: a header row, then one row per Work
 * Entry with its date, Employee, Project, Client, hours, description, state,
 * and who approved or declined it, when, and why it was declined.
 * The file is written row by row, so a long list never sits in memory.
 */
class WorkEntrySpreadsheet
{
    private const array HEADERS = [
        'Date', 'Employee', 'Project', 'Client', 'Hours', 'Description', 'State',
        'Approved by', 'Approved on', 'Declined by', 'Declined on', 'Decline reason',
    ];

    /**
     * The Work Entries, in the query's order, as a download named after the
     * Period, such as "work-entries-2026-11-01-to-2026-11-30.csv". The query
     * needs each Work Entry's Employee, Project, Client, approver and decliner
     * loaded.
     */
    public static function download(WorkEntryBuilder $entries, SpreadsheetFormat $format, Period $period): StreamedResponse
    {
        $name = sprintf('work-entries-%s-to-%s', $period->from->toDateString(), $period->to->toDateString());

        return Spreadsheet::download($name, $format, self::HEADERS, (function () use ($entries, $format): iterable {
            foreach ($entries->lazy(500) as $entry) {
                yield self::cells($entry, $format);
            }
        })());
    }

    /**
     * One Work Entry's cells, in the order of the headers.
     *
     * @return list<Cell>
     */
    private static function cells(WorkEntry $entry, SpreadsheetFormat $format): array
    {
        return [
            Spreadsheet::date($entry->date, $format),
            Spreadsheet::text($entry->assignment->employee->name, $format),
            Spreadsheet::text($entry->assignment->project->name, $format),
            Spreadsheet::text($entry->assignment->project->client->name ?? '', $format),
            Cell::fromValue($entry->hours),
            Spreadsheet::text($entry->description, $format),
            Spreadsheet::text(ucfirst($entry->state->value), $format),
            Spreadsheet::text($entry->approver->name ?? '', $format),
            Spreadsheet::date($entry->approved_at, $format),
            Spreadsheet::text($entry->decliner->name ?? '', $format),
            Spreadsheet::date($entry->declined_at, $format),
            Spreadsheet::text($entry->decline_reason ?? '', $format),
        ];
    }
}
