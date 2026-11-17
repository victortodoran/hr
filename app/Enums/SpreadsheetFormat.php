<?php

namespace App\Enums;

use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * The kinds of file an export can be downloaded as: CSV, which any
 * spreadsheet opens, or Excel.
 */
enum SpreadsheetFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    /**
     * A writer for a new file in this format.
     */
    public function writer(): WriterInterface
    {
        return match ($this) {
            self::Csv => new CsvWriter,
            self::Xlsx => new XlsxWriter,
        };
    }
}
