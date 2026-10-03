<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            \Illuminate\Support\Facades\DB::connection()->getPdo()->sqliteCreateFunction('concat', function (...$args) {
                return implode('', $args);
            });
        }
    }
}
