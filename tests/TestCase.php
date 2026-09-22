<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Pin app root to this checkout. Symlinked vendor otherwise makes
        // Application::inferBasePath() resolve to the vendor host worktree.
        $base = dirname(__DIR__);
        $_ENV['APP_BASE_PATH'] = $base;
        $_SERVER['APP_BASE_PATH'] = $base;
        putenv('APP_BASE_PATH='.$base);

        parent::setUp();
    }
}
