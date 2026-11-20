<?php

namespace App\Models;

use App\Enums\AccessChange;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rows are only ever added, and the app never shows them.
 *
 * @property int $id
 * @property AccessChange $change
 * @property int $actor_id
 * @property int|null $role_id
 * @property string $role_name
 * @property int|null $employee_id
 * @property array{name: string, permissions?: array<string, string|null>, admin?: true, given_to_new_employees: bool, holders?: array<int, int>}|null $before
 * @property array{name: string, permissions?: array<string, string|null>, admin?: true, given_to_new_employees: bool}|null $after
 * @property Carbon|null $created_at
 */
#[Fillable(['change', 'actor_id', 'role_id', 'role_name', 'employee_id', 'before', 'after'])] // @pest-mutate-ignore (class attributes never show as covered)
class AccessLogEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change' => AccessChange::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
