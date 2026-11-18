<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Employee;
use App\Models\WorkEntry;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
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
     * The Work Entries the Work Entries page lists for the viewer under the
     * filters, oldest first like the page, as a download named after the
     * Period, such as "work-entries-2026-11-01-to-2026-11-30.csv".
     *
     * @param  array{period: Period, employee: int|'all'|null, project: int|null, state: string|null}  $filters
     */
    public static function download(Employee $viewer, array $filters, SpreadsheetFormat $format): StreamedResponse
    {
        $period = $filters['period'];
        $name = sprintf('work-entries-%s-to-%s.%s', $period->from->toDateString(), $period->to->toDateString(), $format->value);
        $entries = WorkEntry::query()
            ->listedFor($viewer, $filters)
            ->with(['assignment.employee:id,name', 'assignment.project:id,name,client_id', 'assignment.project.client:id,name', 'approver:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same rows)
            ->orderBy('date')
            ->orderBy('id');

        return response()->streamDownload(function () use ($entries, $format): void {
            $writer = $format->writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(self::HEADERS, (new Style)->withFontBold(true)));

            foreach ($entries->lazy(500) as $entry) {
                $writer->addRow(new Row(self::cells($entry, $format)));
            }

            $writer->close();
        }, $name, ['Content-Type' => $format->contentType()]);
    }

    /**
     * One Work Entry's cells, in the order of the headers.
     *
     * @return list<Cell>
     */
    private static function cells(WorkEntry $entry, SpreadsheetFormat $format): array
    {
        return [
            self::date($entry->date, $format),
            self::text($entry->assignment->employee->name, $format),
            self::text($entry->assignment->project->name, $format),
            self::text($entry->assignment->project->client->name ?? '', $format),
            Cell::fromValue($entry->hours),
            self::text($entry->description, $format),
            self::text(ucfirst($entry->state->value), $format),
            self::text($entry->approver->name ?? '', $format),
            self::date($entry->approved_at, $format),
            self::text($entry->decliner->name ?? '', $format),
            self::date($entry->declined_at, $format),
            self::text($entry->decline_reason ?? '', $format),
        ];
    }

    /**
     * A day as a date cell in Excel, formatted like 2026-11-03, or as that
     * text in a CSV file, which has no dates; empty when there is none.
     */
    private static function date(?DateTimeInterface $day, SpreadsheetFormat $format): Cell
    {
        return match (true) {
            $day === null => Cell::fromValue(null),
            $format === SpreadsheetFormat::Xlsx => new Cell\DateTimeCell($day, (new Style)->withFormat('yyyy-mm-dd')),
            default => Cell::fromValue($day->format('Y-m-d')),
        };
    }

    /**
     * Text as text. Excel keeps it so; in a CSV file, text starting like a
     * formula (=, +, - or @, or their full-width forms, even after spaces, or
     * a tab or a carriage return) gets a leading apostrophe, so a description such as "=HYPERLINK(...)" doesn't run
     * when the file is opened in a spreadsheet.
     */
    private static function text(string $text, SpreadsheetFormat $format): Cell
    {
        if ($format === SpreadsheetFormat::Csv && preg_match('/^\s*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]|^[\t\r]/u', $text) === 1) {
            $text = "'".$text;
        }

        return new Cell\StringCell($text, null);
    }
}
