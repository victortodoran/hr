<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\WorkEntryBuilder;
use App\Models\WorkEntry;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Work Entries as a file to download: a header row, then one row per Work
 * Entry with its date, Employee, Project, Client, hours, description, state,
 * and who approved, locked or declined it, when, and why it was declined.
 * The file is written row by row, so a long list never sits in memory.
 */
class WorkEntrySpreadsheet
{
    private const array HEADERS = [
        'Date', 'Employee', 'Project', 'Client', 'Hours', 'Description', 'State',
        'Approved by', 'Approved on', 'Locked by', 'Locked on', 'Declined by', 'Declined on', 'Decline reason',
    ];

    /**
     * The Work Entries, in the query's order, as a download named after the
     * Period, such as "work-entries-2026-11-01-to-2026-11-30.csv". The query
     * needs each Work Entry's Employee, Project, Client, approver, locker and
     * decliner loaded.
     */
    public static function download(WorkEntryBuilder $entries, SpreadsheetFormat $format, Period $period): StreamedResponse
    {
        $name = sprintf('work-entries-%s-to-%s.%s', $period->from->toDateString(), $period->to->toDateString(), $format->value);

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
            self::text($entry->locker->name ?? '', $format),
            self::date($entry->locked_at, $format),
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
