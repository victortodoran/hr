<?php

namespace App\Models;

use App\Support\Currency;
use Database\Factories\CompanySettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The installation's company (ADR 0003): its name and the single currency
 * every Pay and Travel Expense is recorded in. There is at most one row.
 *
 * @property int $id
 * @property string $name
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'currency'])] // @pest-mutate-ignore (class attributes never show as covered)
#[Table('company_settings')]
class CompanySettings extends Model
{
    /** @use HasFactory<CompanySettingsFactory> */
    use HasFactory;

    /**
     * The installation's company settings, or null before installation.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * Whether the currency can no longer change, because Pay or Travel
     * Expenses have been recorded in it.
     */
    public static function isCurrencyLocked(): bool
    {
        return PayEntry::query()->exists() || TravelExpense::query()->exists();
    }

    /**
     * The currency every Pay and Travel Expense is recorded in. The company is installed
     * before anyone can sign in, so its settings always exist by then.
     */
    public static function payCurrency(): Currency
    {
        return new Currency(static::query()->sole()->currency);
    }
}
