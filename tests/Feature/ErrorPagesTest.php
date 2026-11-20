<?php

use App\Enums\Permission;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Error pages render through Inertia only with debugging off, as in
 * production.
 */
beforeEach(fn () => config(['app.debug' => false]));

it('shows a missing page as the Error page, signed out, with the app name', function () {
    get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('name', config('app.name')));
});

it('shows a refused page as the Error page with a 403', function () {
    actingAs(employeeWith([Permission::ManageDepartments->value => null]))
        ->get(route('approvals.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
});

it('shows a failed page to a signed-in Employee as the Error page, without the exception or the database-backed shared props', function () {
    Route::middleware('web')->get('/failing-page', fn () => throw new RuntimeException('Broken'));

    $response = actingAs(employeeWith([Permission::ViewDirectory->value => null]))->get('/failing-page');

    $response->assertInternalServerError()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 500)
            ->missing('auth'));
    expect($response->content())->not->toContain('Broken');
});

it('shows a failed page as the Error page with a 500', function () {
    Route::middleware('web')->get('/failing-page', fn () => throw new RuntimeException('Broken'));

    get('/failing-page')
        ->assertInternalServerError()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 500));
});

it('keeps Laravel\'s own error responses while debugging and for JSON requests', function () {
    config(['app.debug' => true]);
    expect(get('/no-such-page')->assertNotFound()->content())->not->toContain('"component":"Error"');

    config(['app.debug' => false]);
    get('/no-such-page', ['Accept' => 'application/json'])->assertNotFound()->assertJsonStructure(['message']);
});
