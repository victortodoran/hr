<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\NameSearchRequest;
use App\Models\Employee;
use App\Pages\Search\CommandMenuResults;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Answers the command menu's search (⌘K) from any page: people, Projects
 * and Clients by name, only those the viewer could already open.
 */
class CommandMenuController extends Controller
{
    /**
     * Find the people, Projects and Clients whose name holds the search.
     */
    public function __invoke(#[CurrentUser] Employee $viewer, NameSearchRequest $request): JsonResponse
    {
        return response()->json(CommandMenuResults::props($viewer, $request->search()));
    }
}
