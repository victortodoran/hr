<?php

namespace App\Listeners;

use App\Models\Employee;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RefuseSignInOutsideEmployment
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof Employee) { // @pest-mutate-ignore (every signed-in account is an Employee, so this guard never returns)
            return; // @pest-mutate-ignore (every signed-in account is an Employee, so this guard never returns)
        }

        $refusal = $event->user->signInRefusal();

        if ($refusal === null) {
            return;
        }

        Auth::guard($event->guard)->logout();

        throw ValidationException::withMessages(['email' => $refusal]);
    }
}
