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
 * There is at most one row (ADR 0003).
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

    public static function current(): ?self
    {
        return static::query()->first();
    }

    public static function isCurrencyLocked(): bool
    {
        return PayEntry::query()->exists() || TravelExpense::query()->exists();
    }

    /** The company is installed before anyone can sign in, so its settings always exist by then. */
    public static function payCurrency(): Currency
    {
        return new Currency(static::query()->sole()->currency);
    }
}
