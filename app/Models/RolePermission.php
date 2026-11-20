<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Scope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
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
