<?php

namespace App\Http\Controllers\TimeOff;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffPreviewRequest;
use App\Pages\TimeOff\TimeOffPreviewPage;
use Illuminate\Http\JsonResponse;

/**
 * The live count in the Time Off request dialog.
 */
class TimeOffPreviewController extends Controller
{
    /**
     * What requesting Time Off would take: its weekdays and, when Paid, for
     * each year it touches the Paid Days it would use and those left after
     * it. Only the signed-in Employee's own Paid Days are previewed; overlaps
     * are checked on requesting.
     */
    public function show(TimeOffPreviewRequest $request): JsonResponse
    {
        return response()->json(TimeOffPreviewPage::props($request->unsavedTimeOff(), $request->employee()));
    }
}
