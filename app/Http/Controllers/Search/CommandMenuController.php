<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\NameSearchRequest;
use App\Models\Employee;
use App\Pages\Search\CommandMenuResults;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class CommandMenuController extends Controller
{
    public function __invoke(#[CurrentUser] Employee $viewer, NameSearchRequest $request): JsonResponse
    {
        return response()->json(CommandMenuResults::props($viewer, $request->search()));
    }
}
