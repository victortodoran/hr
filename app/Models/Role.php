<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named, customizable set of Permissions (ADR 0004).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_given_to_new_employees
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_given_to_new_employees'])] // @pest-mutate-ignore (class attributes never show as covered)
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * The Role's Permissions in the fixed Permission order, each mapped to
     * its Scope, or to null for company-wide Permissions.
     *
     * @return array<string, string|null>
     */
    public function grants(): array
    {
        $scopes = $this->permissions->mapWithKeys(fn (RolePermission $grant): array => [$grant->permission->value => $grant->scope?->value]);

        return collect(Permission::cases())
            ->filter(fn (Permission $permission): bool => $scopes->has($permission->value))
            ->mapWithKeys(fn (Permission $permission): array => [$permission->value => $scopes->get($permission->value)])
            ->all();
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)->withTimestamps();
    }
}
