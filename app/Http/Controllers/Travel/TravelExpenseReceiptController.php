<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Models\TravelExpense;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a Travel Expense's receipt from the private disk, to those who may
 * see the Travel Expense (TravelExpensePolicy::view).
 */
class TravelExpenseReceiptController extends Controller
{
    /**
     * Show the receipt in the browser.
     */
    public function show(TravelExpense $travelExpense): StreamedResponse
    {
        return Storage::disk(TravelExpense::RECEIPT_DISK)->response($travelExpense->receipt_path);
    }
}
