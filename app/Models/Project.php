<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A piece of work Employees can be assigned to, optionally for a Client.
 * The one Internal Project (ADR 0002) is work for the company itself: it has
 * no Client and no dates, because it runs for as long as the company does.
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
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
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
            'is_internal' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Who works on the Project, and when.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Why an Assignment over the period wouldn't fit the Project's dates, by
     * the field to blame; empty when it fits. A null end means the
     * Assignment is open-ended. The Internal Project has no dates, so
     * anything fits it.
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

    /**
     * Whether the Project ended before today, so nobody new can join it.
     */
    public function hasFinished(): bool
    {
        return $this->end_date?->isBefore(today()) ?? false;
    }

    /**
     * The Project's dates as the pages show them.
     *
     * @return array{start_date: string|null, end_date: string|null}
     */
    public function dates(): array
    {
        return [
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
