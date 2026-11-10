<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The installation's company (ADR 0003): its name and the single currency
 * every Pay is recorded in. There is at most one row.
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
    /**
     * The installation's company settings, or null before installation.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
