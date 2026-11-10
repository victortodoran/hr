<?php

use function Pest\Laravel\get;

it('shows the home page to guests', function () {
    get('/')->assertOk();
});
