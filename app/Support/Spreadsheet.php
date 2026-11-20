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

/** Rows are written one by one, so a long list never sits in memory. */
final class Spreadsheet
{
    /**
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

    /** A CSV file has no dates, so there the day is text. */
    public static function date(?DateTimeInterface $day, SpreadsheetFormat $format): Cell
    {
        return match (true) {
            $day === null => Cell::fromValue(null),
            $format === SpreadsheetFormat::Xlsx => new Cell\DateTimeCell($day, (new Style)->withFormat('yyyy-mm-dd')),
            default => Cell::fromValue($day->format('Y-m-d')),
        };
    }

    /** In a CSV file, text starting like a formula gets a leading apostrophe, so it doesn't run when the file is opened in a spreadsheet. */
    public static function text(string $text, SpreadsheetFormat $format): Cell
    {
        if ($format === SpreadsheetFormat::Csv && preg_match('/^\s*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]|^[\t\r]/u', $text) === 1) {
            $text = "'".$text;
        }

        return new Cell\StringCell($text, null);
    }
}
