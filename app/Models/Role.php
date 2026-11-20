<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Scope;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_admin
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
            'is_given_to_new_employees' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
        ];
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Admin stores no grants but holds every Permission, with everyone for those about Employees.
     *
     * @return array<string, string|null>
     */
    public function grants(): array
    {
        if ($this->is_admin) {
            return collect(Permission::cases())
                ->mapWithKeys(fn (Permission $permission): array => [$permission->value => $permission->isScoped() ? Scope::Everyone->value : null])
                ->all();
        }

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
