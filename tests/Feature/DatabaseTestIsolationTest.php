<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTestIsolationTest extends TestCase
{
    public function test_phpunit_is_pinned_to_the_local_postgres_testing_database(): void
    {
        $connection = config('database.default');
        $configuration = config("database.connections.{$connection}");

        $this->assertSame('pgsql', $connection);
        $this->assertSame('pgsql', $configuration['host']);
        $this->assertSame('testing', $configuration['database']);
        $this->assertSame('public', $configuration['search_path']);
        $this->assertSame('prefer', $configuration['sslmode']);
        $this->assertStringNotContainsString('supabase.co', $configuration['host']);
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }
}
