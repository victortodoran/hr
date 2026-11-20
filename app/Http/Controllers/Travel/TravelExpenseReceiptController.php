<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Models\TravelExpense;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelExpenseReceiptController extends Controller
{
    public function show(TravelExpense $travelExpense): StreamedResponse
    {
        $disk = Storage::disk(TravelExpense::RECEIPT_DISK);
        abort_unless($disk->exists($travelExpense->receipt_path), 404);

        return $disk->response($travelExpense->receipt_path);
    }
}
