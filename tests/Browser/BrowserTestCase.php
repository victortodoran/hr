<?php

namespace Tests\Browser;

use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    /** Browser tests load the Vite manifest that feature tests leave out, so run `npm run build` before them. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withVite();
    }
}
