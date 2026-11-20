<?php

namespace App\Support;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\CompanySettings;
use App\Models\TravelExpense;
use OpenSpout\Common\Entity\Cell;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelExpenseSpreadsheet
{
    /** The query needs each Travel Expense's Travel Request and its Employee loaded. */
    public static function download(TravelExpenseBuilder $expenses, SpreadsheetFormat $format, Period $period): StreamedResponse
    {
        $currency = CompanySettings::payCurrency();
        $headers = ['Employee', 'Destination', 'First date', 'Last date', 'Paid on', 'Category', 'Description', "Amount ({$currency->code})"];

        return Spreadsheet::download('travel-expenses', $period, $format, $headers, $expenses, fn (TravelExpense $expense): array => self::cells($expense, $format, $currency));
    }

    /** @return list<Cell> */
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
