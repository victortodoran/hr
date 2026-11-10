<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature test closures are bound to the application's TestCase, so they
| can call routes and use Laravel's testing helpers.
|
*/

pest()->extend(TestCase::class)->in('Feature');
