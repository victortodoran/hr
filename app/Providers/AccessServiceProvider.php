<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Define one Gate per Permission (ADR 0004): a scoped Permission is
     * checked against a target Employee, passing when any Scope the acting
     * Employee's Roles give it covers them (Employee::reachesThrough()); the
     * rest apply company-wide to whoever holds them.
     */
    public function boot(): void
    {
        foreach (Permission::cases() as $permission) {
            if ($permission->isScoped()) {
                Gate::define($permission->value, fn (Employee $actor, Employee $target): bool => $actor->reachesThrough([$permission], $target));
            } else {
                Gate::define($permission->value, fn (Employee $actor): bool => $actor->holds($permission));
            }
        }
    }
}
