<?php

namespace App\Http\Controllers\TimeOff;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffPreviewRequest;
use App\Pages\TimeOff\TimeOffPreviewPage;
use Illuminate\Http\JsonResponse;

class TimeOffPreviewController extends Controller
{
    public function show(TimeOffPreviewRequest $request): JsonResponse
    {
        return response()->json(TimeOffPreviewPage::props($request->unsavedTimeOff(), $request->employee()));
    }
}
