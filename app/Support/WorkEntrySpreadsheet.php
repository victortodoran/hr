<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\WorkEntryBuilder;
use App\Models\WorkEntry;
use OpenSpout\Common\Entity\Cell;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkEntrySpreadsheet
{
    private const array HEADERS = [
        'Date', 'Employee', 'Project', 'Client', 'Hours', 'Description', 'State',
        'Approved by', 'Approved on', 'Declined by', 'Declined on', 'Decline reason',
    ];

    /** The query needs each Work Entry's Employee, Project, Client, approver and decliner loaded. */
    public static function download(WorkEntryBuilder $entries, SpreadsheetFormat $format, Period $period): StreamedResponse
    {
        return Spreadsheet::download('work-entries', $period, $format, self::HEADERS, $entries, fn (WorkEntry $entry): array => self::cells($entry, $format));
    }

    /** @return list<Cell> */
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
