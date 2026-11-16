<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Define one Gate per Permission (ADR 0004), each asking how far the
     * acting Employee's Roles let it reach (Employee::reachOf()): a scoped
     * Permission is checked against a target Employee, the rest apply
     * company-wide.
     */
    public function boot(): void
    {
        foreach (Permission::cases() as $permission) {
            if ($permission->isScoped()) {
                Gate::define($permission->value, fn (Employee $actor, Employee $target): bool => $actor->reachesThrough([$permission], $target));
            } else {
                Gate::define($permission->value, fn (Employee $actor): bool => $actor->reachOf($permission) !== null);
            }
        }
    }
}
