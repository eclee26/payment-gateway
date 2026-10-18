<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views render without needing `npm run build` output.
        $this->withoutVite();
    }

    /**
     * RefreshDatabase drops every table. Refuse to run unless the tests are
     * on the in-memory SQLite database, so a misconfigured environment can
     * never wipe a real database.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Refusing to refresh the [{$connection}] database [{$database}]; tests must use in-memory SQLite.");
        }
    }
}
