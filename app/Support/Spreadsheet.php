<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An export as a file to download, shared by every export: a bold header
 * row, then the rows, written one by one so a long list never sits in
 * memory, with cells for dates and text that read the same way in each.
 */
final class Spreadsheet
{
    /**
     * The rows under the headers as a download with the given name, its
     * extension added from the format. The rows are only read while the
     * file is sent, so a lazy iterable keeps them out of memory.
     *
     * @param  list<string>  $headers
     * @param  iterable<list<Cell>>  $rows
     */
    public static function download(string $name, SpreadsheetFormat $format, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($format, $headers, $rows): void {
            $writer = $format->writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));

            foreach ($rows as $cells) {
                $writer->addRow(new Row($cells));
            }

            $writer->close();
        }, "{$name}.{$format->value}", ['Content-Type' => $format->contentType()]);
    }

    /**
     * A day as a date cell in Excel, formatted like 2026-11-03, or as that
     * text in a CSV file, which has no dates; empty when there is none.
     */
    public static function date(?DateTimeInterface $day, SpreadsheetFormat $format): Cell
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
    public static function text(string $text, SpreadsheetFormat $format): Cell
    {
        if ($format === SpreadsheetFormat::Csv && preg_match('/^\s*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]|^[\t\r]/u', $text) === 1) {
            $text = "'".$text;
        }

        return new Cell\StringCell($text, null);
    }
}
