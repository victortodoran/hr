<?php

namespace App\Models;

use App\Models\Builders\AssignmentBuilder;
use Carbon\CarbonInterface;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $project_id
 * @property string|null $job_title Only ever empty on the Internal Assignment
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int $capacity The whole percentage of the Employee's time, from 0
 *                         to 100, that the Assignment is set to take, always set by hand
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Project $project
 * @property-read Collection<int, PayEntry> $payEntries
 * @property-read Collection<int, WorkEntry> $workEntries
 * @property-read Collection<int, TravelRequest> $travelRequests
 * @property-read bool $work_entries_exists Only when loaded with withExists()
 * @property-read bool $travel_requests_exists Only when loaded with withExists()
 */
#[Fillable(['project_id', 'job_title', 'start_date', 'end_date', 'capacity'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(AssignmentBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<PayEntry, $this> */
    public function payEntries(): HasMany
    {
        return $this->hasMany(PayEntry::class);
    }

    /** @return HasMany<WorkEntry, $this> */
    public function workEntries(): HasMany
    {
        return $this->hasMany(WorkEntry::class);
    }

    /** @return HasMany<TravelRequest, $this> */
    public function travelRequests(): HasMany
    {
        return $this->hasMany(TravelRequest::class);
    }

    public function isInternal(): bool
    {
        return $this->project->is_internal;
    }

    /** The end date counts as a day of the Assignment, as in AssignmentBuilder::notEndedBefore(). */
    public function hasEndedBefore(CarbonInterface $day): bool
    {
        return $this->end_date !== null && $this->end_date->isBefore($day->copy()->startOfDay());
    }

    /** Must match AssignmentBuilder::runningOn(). */
    public function isRunningOn(CarbonInterface $day): bool
    {
        return ! $this->start_date->isAfter($day->copy()->startOfDay()) && ! $this->hasEndedBefore($day);
    }

    /** Reads `withExists(['workEntries', 'travelRequests'])` when the query loaded it, so a list asks once rather than per Assignment. */
    public function canBeDeleted(): bool
    {
        $hasWorkEntries = $this->hasAttribute('work_entries_exists') ? $this->work_entries_exists : $this->workEntries()->exists();
        $hasTravelRequests = $this->hasAttribute('travel_requests_exists') ? $this->travel_requests_exists : $this->travelRequests()->exists();

        return ! $this->isInternal() && ! $hasWorkEntries && ! $hasTravelRequests;
    }

    public function dropPayAfterEnd(): void
    {
        if ($this->end_date === null) {
            return;
        }

        $this->payEntries()->whereDate('effective_from', '>', $this->end_date)->delete();
    }

    public function movePayToStart(): void
    {
        $inForce = $this->payEntries()
            ->whereDate('effective_from', '<=', $this->start_date)
            ->latest('effective_from')
            ->first();

        if ($inForce === null) {
            return;
        }

        $this->payEntries()->whereDate('effective_from', '<', $inForce->effective_from)->delete();
        $inForce->update(['effective_from' => $this->start_date]);
    }
}
