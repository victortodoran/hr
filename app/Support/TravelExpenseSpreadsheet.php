<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\CompanySettings;
use App\Models\TravelExpense;
use OpenSpout\Common\Entity\Cell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Travel Expenses as a file to download, for paying them back: a header
 * row, then one row per Travel Expense with its Employee, its Travel
 * Request's destination and dates, the date it was paid, its Travel
 * Expense Category, description and amount in the company currency.
 * The file is written row by row, so a long list never sits in memory.
 */
class TravelExpenseSpreadsheet
{
    /**
     * The Travel Expenses, in the query's order, as a download named after
     * the Period, such as "travel-expenses-2026-11-01-to-2026-11-30.csv".
     * The query needs each Travel Expense's Travel Request and its Employee
     * loaded.
     */
    public static function download(TravelExpenseBuilder $expenses, SpreadsheetFormat $format, Period $period): StreamedResponse
    {
        $currency = CompanySettings::payCurrency();
        $name = sprintf('travel-expenses-%s-to-%s', $period->from->toDateString(), $period->to->toDateString());
        $headers = ['Employee', 'Destination', 'First date', 'Last date', 'Paid on', 'Category', 'Description', "Amount ({$currency->code})"];

        return Spreadsheet::download($name, $format, $headers, (function () use ($expenses, $format, $currency): iterable {
            foreach ($expenses->lazy(500) as $expense) {
                yield self::cells($expense, $format, $currency);
            }
        })());
    }

    /**
     * One Travel Expense's cells, in the order of the headers, its amount
     * a number in the currency's units, such as 1990.5.
     *
     * @return list<Cell>
     */
    private static function cells(TravelExpense $expense, SpreadsheetFormat $format, Currency $currency): array
    {
        return [
            Spreadsheet::text($expense->travelRequest->employee->name, $format),
            Spreadsheet::text($expense->travelRequest->destination, $format),
            Spreadsheet::date($expense->travelRequest->first_date, $format),
            Spreadsheet::date($expense->travelRequest->last_date, $format),
            Spreadsheet::date($expense->paid_on, $format),
            Spreadsheet::text(ucfirst($expense->category->value), $format),
            Spreadsheet::text($expense->description, $format),
            Cell::fromValue($expense->amount / 10 ** $currency->decimals()),
        ];
    }
}
