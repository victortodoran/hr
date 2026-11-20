<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pages render without the Vite manifest, so a new page's tests pass
     * before the frontend is built. CI still builds the frontend on its own.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
