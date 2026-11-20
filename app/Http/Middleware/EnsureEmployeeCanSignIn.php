<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session outlives the day an Employee may no longer sign in, so they are
 * signed out on their next request.
 */
class EnsureEmployeeCanSignIn
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();
        $refusal = $employee instanceof Employee ? $employee->signInRefusal() : null;

        if ($refusal === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken(); // @pest-mutate-ignore (invalidate() already drops the token; kept as Laravel's standard logout)

        return to_route('login')->withErrors(['email' => $refusal]);
    }
}
