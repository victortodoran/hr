<?php

namespace App\Models;

use App\Models\Builders\ProjectBuilder;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * The Internal Project has no Client and no dates, as it runs for as long as the company does (ADR 0002).
 *
 * @property int $id
 * @property string $name
 * @property int|null $client_id
 * @property Carbon|null $start_date Only null on the Internal Project
 * @property Carbon|null $end_date
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'client_id', 'start_date', 'end_date'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(ProjectBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_internal' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
        ];
    }

    /** Created at install, so sole() always finds it. */
    public static function internal(): self
    {
        return self::query()->internal()->sole();
    }

    /** @return array<int, array{id: int, name: string}> */
    public static function choices(): array
    {
        return self::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => $project->only('id', 'name'))
            ->all();
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasManyThrough<WorkEntry, Assignment, $this> */
    public function workEntries(): HasManyThrough
    {
        return $this->hasManyThrough(WorkEntry::class, Assignment::class);
    }

    /** @return HasManyThrough<TravelRequest, Assignment, $this> */
    public function travelRequests(): HasManyThrough
    {
        return $this->hasManyThrough(TravelRequest::class, Assignment::class);
    }

    /**
     * The Internal Project has no dates, so anything fits it.
     *
     * @return array<'start_date'|'end_date', string>
     */
    public function assignmentRefusals(CarbonInterface $start, ?CarbonInterface $end): array
    {
        $refusals = [];

        if ($this->start_date !== null && $start->isBefore($this->start_date)) {
            $refusals['start_date'] = __(':project starts on :date, so the Assignment can\'t start before it.', ['project' => $this->name, 'date' => $this->start_date->format('j M Y')]);
        }

        if ($this->end_date !== null && ($end === null || $end->isAfter($this->end_date))) {
            $refusals['end_date'] = __(':project ends on :date, so the Assignment must end by then.', ['project' => $this->name, 'date' => $this->end_date->format('j M Y')]);
        }

        return $refusals;
    }

    public function hasFinished(): bool
    {
        return $this->end_date?->isBefore(today()) ?? false;
    }

    /** @return array{start_date: string|null, end_date: string|null} */
    public function dates(): array
    {
        return [
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
