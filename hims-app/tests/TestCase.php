<?php

namespace Tests;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection() instanceof SQLiteConnection) {
            DB::connection()->getPdo()->sqliteCreateFunction('concat', function (...$args) {
                return implode('', $args);
            });
        }
    }
}
