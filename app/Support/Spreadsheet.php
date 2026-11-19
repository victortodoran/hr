<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * The query's models, in its order, as a download named after what it
     * holds and the Period, such as "work-entries-2026-11-01-to-2026-11-30.csv":
     * the headers, then one row per model of the cells it gives. The models
     * are read 500 at a time while the file is sent.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $headers
     * @param  Closure(TModel): list<Cell>  $cells
     */
    public static function download(string $holding, Period $period, SpreadsheetFormat $format, array $headers, Builder $query, Closure $cells): StreamedResponse
    {
        $name = sprintf('%s-%s-to-%s.%s', $holding, $period->from->toDateString(), $period->to->toDateString(), $format->value);

        return response()->streamDownload(function () use ($format, $headers, $query, $cells): void {
            $writer = $format->writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));

            foreach ($query->lazy(500) as $model) {
                $writer->addRow(new Row($cells($model)));
            }

            $writer->close();
        }, $name, ['Content-Type' => $format->contentType()]);
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
     * a tab or a carriage return) gets a leading apostrophe, so a description
     * such as "=HYPERLINK(...)" doesn't run when the file is opened in a
     * spreadsheet.
     */
    public static function text(string $text, SpreadsheetFormat $format): Cell
    {
        if ($format === SpreadsheetFormat::Csv && preg_match('/^\s*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]|^[\t\r]/u', $text) === 1) {
            $text = "'".$text;
        }

        return new Cell\StringCell($text, null);
    }
}
