<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class TransferDatabaseToPostgres extends Command
{
    private int $targetMigrationBaselineSegments = 0;

    protected $signature = 'casa:transfer-to-postgres
        {--source=migration_source : Read-only source connection name}
        {--target=migration_target : Empty PostgreSQL target connection name}
        {--chunk=500 : Rows inserted per batch}
        {--apply : Copy records and reset target sequences}
        {--validate : Compare an existing target with the source without writing}';

    protected $description = 'Dry-run or apply an account-preserving MariaDB-to-PostgreSQL transfer.';

    /**
     * Tables are ordered so every referenced record exists before its dependents.
     * Runtime state, reset tokens, migration history, and obsolete adjustment data
     * are intentionally absent.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'staff_profiles',
        'customer_profiles',
        'services',
        'staff_services',
        'staff_weekly_schedules',
        'staff_schedule_exceptions',
        'rfm_segments',
        'promotion_rules',
        'promotion_suggestions',
        'application_settings',
        'staff_schedule_weeks',
        'staff_schedule_shifts',
        'appointments',
        'appointment_addons',
        'appointment_status_logs',
        'transactions',
        'therapist_commissions',
        'feedback',
    ];

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);

        if ($chunkSize === false) {
            $this->error('The --chunk value must be an integer from 1 to 5000.');

            return self::FAILURE;
        }

        if ($this->option('apply') && $this->option('validate')) {
            $this->error('Use either --apply or --validate, not both.');

            return self::FAILURE;
        }

        $validate = (bool) $this->option('validate');

        try {
            $source = DB::connection($sourceName);
            $target = DB::connection($targetName);

            $this->assertSeparateDatabases($sourceName, $source, $targetName, $target);
            $counts = $this->inspectSchemasAndCounts($sourceName, $targetName, ! $validate);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Source rows'],
            collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->values()->all(),
        );
        $this->line('Excluded: migrations, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, password_reset_tokens, personal_access_tokens, transaction_adjustments.');

        if ($validate) {
            try {
                $this->validateExistingTransfer($sourceName, $source, $targetName, $target);
            } catch (Throwable $exception) {
                $this->error('Validation failed: '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->info('Validation passed. Every transferred row matches the source and PostgreSQL sequences are aligned.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->info('Dry run passed. The target contains no business data and schemas match. Re-run with --apply to transfer records.');

            return self::SUCCESS;
        }

        try {
            $this->prepareReadOnlySnapshot($source);
            $source->transaction(function () use ($sourceName, $targetName, $target, $chunkSize): void {
                $target->transaction(function () use ($sourceName, $targetName, $target, $chunkSize): void {
                    if ($this->targetMigrationBaselineSegments > 0) {
                        $target->table('rfm_segments')->delete();
                    }

                    foreach (self::TABLES as $table) {
                        $this->copyTable($sourceName, $targetName, $table, $chunkSize);
                    }

                    $this->restoreCommissionAdjustments($sourceName, $targetName);
                    $this->resetPostgresSequences($target);
                });
            });
        } catch (Throwable $exception) {
            $this->error('Transfer rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $expected) {
            $actual = DB::connection($targetName)->table($table)->count();

            if ($actual !== $expected) {
                $this->error("Post-transfer count mismatch for {$table}: expected {$expected}, found {$actual}.");

                return self::FAILURE;
            }
        }

        $this->info('Transfer completed. IDs, password hashes, Google identities, and business records were preserved.');

        return self::SUCCESS;
    }

    private function assertSeparateDatabases(
        string $sourceName,
        Connection $source,
        string $targetName,
        Connection $target,
    ): void {
        if ($sourceName === $targetName || $this->connectionFingerprint($source) === $this->connectionFingerprint($target)) {
            throw new RuntimeException('Source and target must be separate databases.');
        }
    }

    /** @return array<string, int> */
    private function inspectSchemasAndCounts(string $sourceName, string $targetName, bool $requireEmpty): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            if (! Schema::connection($sourceName)->hasTable($table)) {
                throw new RuntimeException("Source table is missing: {$table}. Apply all MariaDB migrations first.");
            }

            if (! Schema::connection($targetName)->hasTable($table)) {
                throw new RuntimeException("Target table is missing: {$table}. Run migrations on PostgreSQL first.");
            }

            $sourceColumns = Schema::connection($sourceName)->getColumnListing($table);
            $targetColumns = Schema::connection($targetName)->getColumnListing($table);
            sort($sourceColumns);
            sort($targetColumns);

            if ($sourceColumns !== $targetColumns) {
                throw new RuntimeException("Schema mismatch for {$table}. Apply the same migrations to source and target.");
            }

            $targetCount = DB::connection($targetName)->table($table)->count();

            if ($requireEmpty && $targetCount > 0) {
                if ($table === 'rfm_segments' && $this->containsOnlyMigrationBaselineSegments($targetName)) {
                    $this->targetMigrationBaselineSegments = $targetCount;
                } else {
                    throw new RuntimeException("Target must be empty; {$table} contains {$targetCount} record(s).");
                }
            }

            $counts[$table] = DB::connection($sourceName)->table($table)->count();
        }

        return $counts;
    }

    private function validateExistingTransfer(
        string $sourceName,
        Connection $source,
        string $targetName,
        Connection $target,
    ): void {
        $this->prepareReadOnlySnapshot($source);
        $source->transaction(function () use ($sourceName, $targetName, $target): void {
            $target->transaction(function () use ($sourceName, $targetName, $target): void {
                foreach (self::TABLES as $table) {
                    $sourceCount = DB::connection($sourceName)->table($table)->count();
                    $targetCount = DB::connection($targetName)->table($table)->count();

                    if ($sourceCount !== $targetCount) {
                        throw new RuntimeException("Count mismatch for {$table}: source {$sourceCount}, target {$targetCount}.");
                    }

                    if ($this->tableFingerprint($sourceName, $targetName, $table) !== $this->tableFingerprint($targetName, $targetName, $table)) {
                        throw new RuntimeException("Record mismatch for {$table}.");
                    }
                }

                $this->validatePostgresSequences($target);
            });
        });
    }

    private function tableFingerprint(string $connectionName, string $targetName, string $table): string
    {
        $booleanColumns = $this->booleanColumns($targetName, $table);
        $hash = hash_init('sha256');

        DB::connection($connectionName)
            ->table($table)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($booleanColumns, $hash): void {
                foreach ($rows as $row) {
                    $record = (array) $row;

                    foreach ($booleanColumns as $column) {
                        if (array_key_exists($column, $record) && $record[$column] !== null) {
                            $record[$column] = (bool) $record[$column];
                        }
                    }

                    ksort($record);
                    hash_update($hash, json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)."\n");
                }
            });

        return hash_final($hash);
    }

    private function containsOnlyMigrationBaselineSegments(string $targetName): bool
    {
        $expectedKeys = collect(config('casa.customer_rewards.presets', []))
            ->pluck('key')
            ->sort()
            ->values()
            ->all();
        $actualKeys = DB::connection($targetName)
            ->table('rfm_segments')
            ->pluck('preset_key')
            ->sort()
            ->values()
            ->all();

        return $expectedKeys !== [] && $actualKeys === $expectedKeys;
    }

    private function prepareReadOnlySnapshot(Connection $source): void
    {
        if (in_array($source->getDriverName(), ['mysql', 'mariadb'], true)) {
            $source->statement('SET TRANSACTION READ ONLY');
        }
    }

    private function copyTable(string $sourceName, string $targetName, string $table, int $chunkSize): void
    {
        $booleanColumns = $this->booleanColumns($targetName, $table);

        DB::connection($sourceName)
            ->table($table)
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use ($targetName, $table, $booleanColumns): void {
                $records = $rows->map(function (object $row) use ($table, $booleanColumns): array {
                    $record = (array) $row;

                    foreach ($booleanColumns as $column) {
                        if (array_key_exists($column, $record) && $record[$column] !== null) {
                            $record[$column] = (bool) $record[$column];
                        }
                    }

                    if ($table === 'therapist_commissions') {
                        $record['adjusts_commission_id'] = null;
                    }

                    return $record;
                })->all();

                if ($records !== []) {
                    DB::connection($targetName)->table($table)->insert($records);
                }
            });
    }

    private function restoreCommissionAdjustments(string $sourceName, string $targetName): void
    {
        DB::connection($sourceName)
            ->table('therapist_commissions')
            ->whereNotNull('adjusts_commission_id')
            ->orderBy('id')
            ->each(function (object $commission) use ($targetName): void {
                DB::connection($targetName)
                    ->table('therapist_commissions')
                    ->where('id', $commission->id)
                    ->update(['adjusts_commission_id' => $commission->adjusts_commission_id]);
            });
    }

    private function resetPostgresSequences(Connection $target): void
    {
        if ($target->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            $quotedTable = $target->getQueryGrammar()->wrapTable($table);
            $quotedId = $target->getQueryGrammar()->wrap('id');
            $target->statement(
                "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE(MAX({$quotedId}), 1), MAX({$quotedId}) IS NOT NULL) FROM {$quotedTable}",
                [$table],
            );
        }
    }

    private function validatePostgresSequences(Connection $target): void
    {
        if ($target->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            $sequence = $target->selectOne("SELECT pg_get_serial_sequence(?, 'id') AS name", [$table])?->name;

            if (! is_string($sequence) || ! preg_match('/^[a-zA-Z0-9_.]+$/', $sequence)) {
                throw new RuntimeException("Unable to resolve the PostgreSQL sequence for {$table}.");
            }

            $state = $target->selectOne('SELECT last_value, is_called FROM '.$target->getQueryGrammar()->wrapTable($sequence));
            $maximumId = $target->table($table)->max('id');
            $expectedValue = $maximumId === null ? 1 : (int) $maximumId;
            $expectedCalled = $maximumId !== null;

            if ((int) $state->last_value !== $expectedValue || (bool) $state->is_called !== $expectedCalled) {
                throw new RuntimeException("PostgreSQL sequence is not aligned for {$table}.");
            }
        }
    }

    /** @return list<string> */
    private function booleanColumns(string $connectionName, string $table): array
    {
        return collect(Schema::connection($connectionName)->getColumns($table))
            ->filter(fn (array $column): bool => str_contains(strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')), 'bool'))
            ->pluck('name')
            ->all();
    }

    private function connectionFingerprint(Connection $connection): string
    {
        $config = $connection->getConfig();

        return implode('|', [
            $config['driver'] ?? '',
            $config['url'] ?? '',
            $config['host'] ?? '',
            $config['port'] ?? '',
            $config['database'] ?? '',
        ]);
    }
}
