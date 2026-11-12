<?php

namespace Tests\Browser;

use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Browser tests drive the built pages in a real browser, so they load
     * the Vite manifest that feature tests leave out. Run `npm run build`
     * before them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withVite();
    }
}
