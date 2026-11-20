<?php

namespace App\Actions\WorkEntries;

use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CopyWorkEntries
{
    /** @param  Collection<int, WorkEntry>  $toCopy */
    public function copy(Collection $toCopy, CarbonImmutable $day): void
    {
        DB::transaction(fn () => $toCopy->each(fn (WorkEntry $entry) => WorkEntry::query()->create([
            'assignment_id' => $entry->assignment_id,
            'date' => $day->toDateString(),
            'minutes' => $entry->minutes,
            'description' => $entry->description,
        ])));
    }
}
