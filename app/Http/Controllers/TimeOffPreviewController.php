<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeOffPreviewRequest;
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
        $timeOff = $request->unsavedTimeOff();
        $years = $timeOff->is_paid ? $request->employee()->paidDaysUsedBy($timeOff) : [];

        return response()->json([
            'weekdays' => $timeOff->weekdays(),
            'years' => array_map(fn (array $year): array => [
                'year' => $year['year'],
                'uses' => $year['uses'],
                'left_after' => $year['left'] - $year['uses'],
            ], $years),
        ]);
    }
}
