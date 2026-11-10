<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Define one Gate per Permission (ADR 0004). A scoped Permission is
     * checked against a target Employee using the widest Scope the acting
     * Employee's Roles give them; the rest apply company-wide.
     */
    public function boot(): void
    {
        foreach (Permission::cases() as $permission) {
            if ($permission->isScoped()) {
                Gate::define($permission->value, fn (Employee $actor, Employee $target): bool => $actor->widestScopeFor($permission)?->covers($actor, $target) ?? false);
            } else {
                Gate::define($permission->value, fn (Employee $actor): bool => $actor->holds($permission));
            }
        }
    }
}
