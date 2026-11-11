<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
