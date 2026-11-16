<?php

namespace App\Models;

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use Database\Factories\TravelExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money an Employee paid themselves for the travel of one of their Travel
 * Requests, which the company pays back (ADR 0007): an amount in the
 * company currency, the date it was paid, a Travel Expense Category, a
 * description and its receipt, kept on the private disk. Like a Work Entry
 * it starts Pending, can be Approved and then Locked for good, or Declined
 * with a reason.
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
 * @property int|null $locked_by
 * @property Carbon|null $locked_at
 * @property int|null $declined_by
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TravelRequest $travelRequest
 */
#[Fillable(['paid_on', 'amount', 'category', 'description', 'receipt_path'])] // @pest-mutate-ignore (class attributes never show as covered)
class TravelExpense extends Model
{
    /** @use HasFactory<TravelExpenseFactory> */
    use HasFactory;

    /**
     * The disk receipts are kept on, which is never publicly reachable:
     * they are only served through the receipt route.
     */
    public const string RECEIPT_DISK = 'local'; // @pest-mutate-ignore (constants are evaluated before coverage starts; the receipt tests pin the disk)

    /**
     * The folder receipts are kept in on that disk.
     */
    public const string RECEIPT_FOLDER = 'travel-expense-receipts'; // @pest-mutate-ignore (constants are evaluated before coverage starts)

    /**
     * Every Travel Expense starts Pending.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Travel Expenses look the same)
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'category' => TravelExpenseCategory::class,
            'state' => TravelExpenseState::class,
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /**
     * Whether the Travel Expense can still be changed or deleted: while
     * Pending, or once Declined, when changing it sends it back to Pending.
     */
    public function isOpen(): bool
    {
        return in_array($this->state, TravelExpenseState::open(), true);
    }

    /**
     * The Travel Request whose travel it was paid for.
     *
     * @return BelongsTo<TravelRequest, $this>
     */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }
}
