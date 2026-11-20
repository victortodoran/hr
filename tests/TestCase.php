<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Pages render without the Vite manifest, so a new page's tests pass before the frontend is built. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
