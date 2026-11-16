<?php

namespace App\Models;

use App\Models\Builders\AssignmentBuilder;
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
 * An Employee working on a Project for a period of time, with a Job Title
 * and a Capacity. It always falls within the Employee's working
 * period and the Project's dates. Every Employee has exactly one Internal
 * Assignment, to the Internal Project, whose dates mirror their Start Date
 * and Leaving Date and whose Job Title is the one the directory shows.
 *
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
 */
#[Fillable(['project_id', 'job_title', 'start_date', 'end_date', 'capacity'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(AssignmentBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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

    /**
     * The Assignment's Pay history.
     *
     * @return HasMany<PayEntry, $this>
     */
    public function payEntries(): HasMany
    {
        return $this->hasMany(PayEntry::class);
    }

    /**
     * The hours worked on the Assignment.
     *
     * @return HasMany<WorkEntry, $this>
     */
    public function workEntries(): HasMany
    {
        return $this->hasMany(WorkEntry::class);
    }

    /**
     * Whether this is the Employee's Internal Assignment.
     */
    public function isInternal(): bool
    {
        return $this->project->is_internal;
    }
}
