<?php

namespace App\Models;

use App\Enums\PayKind;
use Database\Factories\PayEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assignment_id
 * @property Carbon $effective_from
 * @property PayKind $kind
 * @property int $amount In minor units of the company currency, such as cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment $assignment
 */
#[Fillable(['effective_from', 'kind', 'amount'])] // @pest-mutate-ignore (class attributes never show as covered)
class PayEntry extends Model
{
    /** @use HasFactory<PayEntryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'kind' => PayKind::class,
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
