<?php

arch('PHP best practices')->preset()->php();

arch('Laravel conventions')->preset()->laravel()->ignoring(['App\Models\Builders', 'App\Models\Concerns']);

arch('each builder builds queries for its model')
    ->expect('App\Models\Builders')
    ->toExtend('Illuminate\Database\Eloquent\Builder')
    ->toHaveSuffix('Builder');

arch('no insecure functions')->preset()->security();

/*
 * Pest only checks references to classes that exist, so the two rules below
 * stay green until someone installs spatie/laravel-permission or recreates
 * App\Models\User, which are exactly the regressions they guard against.
 */

arch('Roles and Permissions are our own code, not spatie/laravel-permission (ADR 0004)')
    ->expect('App')
    ->not->toUse('Spatie\Permission');

arch('the sign-in model is Employee, not User (ADR 0001)')
    ->expect('App')
    ->not->toUse('App\Models\User');
