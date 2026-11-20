<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Only for an Assignment made by mistake: the Internal Assignment and one
 * with Work Entries or Travel Requests can only be ended.
 */
class DeleteAssignment
{
    /** @throws ValidationException */
    public function delete(Assignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            Assignment::query()->whereKey($assignment->id)->lockForUpdate()->first();

            if (! $assignment->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'assignment' => $assignment->isInternal()
                        ? __('The Internal Assignment can\'t be deleted.')
                        : __('The Assignment to :project has Work Entries or Travel Requests, so it can\'t be deleted. Set an end date instead.', ['project' => $assignment->project->name]),
                ]);
            }

            $assignment->payEntries()->delete();
            $assignment->delete();
        });
    }
}
