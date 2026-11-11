<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Scope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Permission inside a Role, with its Scope when the Permission is about
 * Employees.
 *
 * @property int $id
 * @property int $role_id
 * @property Permission $permission
 * @property Scope|null $scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['permission', 'scope'])] // @pest-mutate-ignore (class attributes never show as covered)
class RolePermission extends Model
{
    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => Permission::class,
            'scope' => Scope::class,
        ];
    }
}
