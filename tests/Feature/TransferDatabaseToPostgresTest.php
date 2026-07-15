<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransferDatabaseToPostgresTest extends TestCase
{
    private const TABLES = [
        'users', 'staff_profiles', 'customer_profiles', 'services', 'staff_services',
        'staff_weekly_schedules', 'staff_schedule_exceptions', 'rfm_segments',
        'promotion_rules', 'promotion_suggestions', 'application_settings',
        'staff_schedule_weeks', 'staff_schedule_shifts', 'appointments',
        'appointment_addons', 'appointment_status_logs', 'transactions',
        'therapist_commissions', 'feedback',
    ];

    private string $sourceDatabase;

    private string $targetDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDatabase = tempnam(sys_get_temp_dir(), 'casa-source-');
        $this->targetDatabase = tempnam(sys_get_temp_dir(), 'casa-target-');

        config([
            'database.connections.transfer_source_test' => [
                'driver' => 'sqlite',
                'database' => $this->sourceDatabase,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.connections.transfer_target_test' => [
                'driver' => 'sqlite',
                'database' => $this->targetDatabase,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $this->createTransferSchemas('transfer_source_test');
        $this->createTransferSchemas('transfer_target_test');
    }

    protected function tearDown(): void
    {
        DB::purge('transfer_source_test');
        DB::purge('transfer_target_test');
        @unlink($this->sourceDatabase);
        @unlink($this->targetDatabase);

        parent::tearDown();
    }

    public function test_dry_run_never_writes_to_the_target(): void
    {
        $this->seedSourceUser();

        $this->artisan('casa:transfer-to-postgres', [
            '--source' => 'transfer_source_test',
            '--target' => 'transfer_target_test',
        ])->expectsOutputToContain('Dry run passed.')
            ->assertSuccessful();

        $this->assertSame(0, DB::connection('transfer_target_test')->table('users')->count());
    }

    public function test_apply_preserves_ids_and_password_hashes(): void
    {
        $passwordHash = $this->seedSourceUser();

        $this->artisan('casa:transfer-to-postgres', [
            '--source' => 'transfer_source_test',
            '--target' => 'transfer_target_test',
            '--apply' => true,
        ])->expectsOutputToContain('Transfer completed.')
            ->assertSuccessful();

        $user = DB::connection('transfer_target_test')->table('users')->first();
        $this->assertSame(41, $user->id);
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame($passwordHash, $user->password);

        $this->artisan('casa:transfer-to-postgres', [
            '--source' => 'transfer_source_test',
            '--target' => 'transfer_target_test',
            '--validate' => true,
        ])->expectsOutputToContain('Validation passed.')
            ->assertSuccessful();
    }

    public function test_transfer_refuses_a_non_empty_target(): void
    {
        DB::connection('transfer_target_test')->table('users')->insert([
            'id' => 1,
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => 'already-there',
        ]);

        $this->artisan('casa:transfer-to-postgres', [
            '--source' => 'transfer_source_test',
            '--target' => 'transfer_target_test',
            '--apply' => true,
        ])->expectsOutputToContain('Target must be empty')
            ->assertFailed();
    }

    public function test_apply_replaces_only_the_known_migration_segment_baseline(): void
    {
        Schema::connection('transfer_source_test')->table('rfm_segments', function (Blueprint $blueprint): void {
            $blueprint->string('preset_key')->nullable();
        });
        Schema::connection('transfer_target_test')->table('rfm_segments', function (Blueprint $blueprint): void {
            $blueprint->string('preset_key')->nullable();
        });

        foreach (config('casa.customer_rewards.presets') as $index => $preset) {
            DB::connection('transfer_target_test')->table('rfm_segments')->insert([
                'id' => $index + 1,
                'preset_key' => $preset['key'],
            ]);
        }
        DB::connection('transfer_source_test')->table('rfm_segments')->insert([
            'id' => 91,
            'preset_key' => config('casa.customer_rewards.presets.0.key'),
        ]);

        $this->artisan('casa:transfer-to-postgres', [
            '--source' => 'transfer_source_test',
            '--target' => 'transfer_target_test',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame([91], DB::connection('transfer_target_test')->table('rfm_segments')->pluck('id')->all());
    }

    private function createTransferSchemas(string $connection): void
    {
        foreach (self::TABLES as $table) {
            Schema::connection($connection)->create($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->unsignedBigInteger('id')->primary();

                if ($table === 'users') {
                    $blueprint->string('name');
                    $blueprint->string('email');
                    $blueprint->string('password');
                }

                if ($table === 'therapist_commissions') {
                    $blueprint->unsignedBigInteger('adjusts_commission_id')->nullable();
                }
            });
        }
    }

    private function seedSourceUser(): string
    {
        $passwordHash = password_hash('secret-password', PASSWORD_BCRYPT);
        DB::connection('transfer_source_test')->table('users')->insert([
            'id' => 41,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => $passwordHash,
        ]);

        return $passwordHash;
    }
}
