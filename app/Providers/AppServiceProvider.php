<?php

namespace App\Providers;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->forgetAccessAfterEachRequest();
    }

    /**
     * A model kept past the request, as actingAs() in tests keeps it, reads its
     * access afresh on the next.
     */
    protected function forgetAccessAfterEachRequest(): void
    {
        Event::listen(RequestHandled::class, function (): void {
            $employee = Auth::hasUser() ? Auth::user() : null;

            if ($employee instanceof Employee) {
                $employee->forgetAccess();
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
