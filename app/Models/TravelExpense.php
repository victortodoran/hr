<?php

namespace App\Models;

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Models\Builders\TravelExpenseBuilder;
use Database\Factories\TravelExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Belongs to an Approved Travel Request (ADR 0007).
 *
 * @property int $id
 * @property int $travel_request_id
 * @property Carbon $paid_on
 * @property int $amount In minor units of the company currency, such as cents
 * @property TravelExpenseCategory $category
 * @property string $description
 * @property string $receipt_path Where the receipt is on the private disk
 * @property TravelExpenseState $state
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $declined_by
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TravelRequest $travelRequest
 * @property-read Employee|null $approver
 * @property-read Employee|null $decliner
 */
#[Fillable(['paid_on', 'amount', 'category', 'description', 'receipt_path'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(TravelExpenseBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class TravelExpense extends Model
{
    /** @use HasFactory<TravelExpenseFactory> */
    use HasFactory;

    /** Never publicly reachable: receipts are only served through the receipt route. */
    public const string RECEIPT_DISK = 'local'; // @pest-mutate-ignore (constants are evaluated before coverage starts; the receipt tests pin the disk)

    public const string RECEIPT_FOLDER = 'travel-expense-receipts'; // @pest-mutate-ignore (constants are evaluated before coverage starts)

    /** @var array<string, string> */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Travel Expenses look the same)
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'category' => TravelExpenseCategory::class,
            'state' => TravelExpenseState::class,
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /** Declined counts, as changing it sends it back to Pending. */
    public function isOpen(): bool
    {
        return in_array($this->state, TravelExpenseState::open(), true);
    }

    public function receiptIsImage(): bool
    {
        return in_array(strtolower(pathinfo($this->receipt_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * The approver is null for an Employee with no Manager, whose Travel Expense was Approved at once. Needs the approver loaded.
     *
     * @return array{by: string|null, on: string}|null
     */
    public function approval(): ?array
    {
        if ($this->approved_at === null) {
            return null;
        }

        return ['by' => $this->approver?->name, 'on' => $this->approved_at->toDateString()];
    }

    /** @return BelongsTo<TravelRequest, $this> */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function decliner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'declined_by');
    }
}
