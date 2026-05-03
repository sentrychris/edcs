<?php

namespace Tests\Feature;

use App\Console\Commands\DatabaseBackupCommand;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (glob(storage_path('database/dumps/unit-dump-*')) ?: [] as $entry) {
            if (is_dir($entry)) {
                rmdir($entry);
            }
        }

        parent::tearDown();
    }

    public function test_fails_when_connection_is_not_mysql(): void
    {
        config(['database.connections.sqlite_test' => ['driver' => 'sqlite']]);

        $this->artisan('db:backup --connection=sqlite_test')
            ->expectsOutputToContain("Connection 'sqlite_test' is not a MySQL connection.")
            ->assertFailed();
    }

    public function test_fails_when_target_directory_already_exists(): void
    {
        $name = 'existing-dump';
        $path = DatabaseBackupCommand::dumpPath($name);
        mkdir($path, 0755, true);

        try {
            $this->artisan("db:backup --name={$name}")
                ->expectsOutputToContain('Dump directory already exists')
                ->assertFailed();
        } finally {
            rmdir($path);
        }
    }

    public function test_invokes_mysqlsh_dump_schemas_with_expected_arguments(): void
    {
        Process::fake([
            '*mysqlsh*' => Process::result(output: 'ok', exitCode: 0),
        ]);

        config([
            'database.connections.mysql.host' => 'db.example',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.username' => 'tester',
            'database.connections.mysql.password' => 'secret',
            'database.connections.mysql.database' => 'edcs_test',
            'database.connections.mysql.backup_username' => null,
            'database.connections.mysql.backup_password' => null,
        ]);

        $name = 'unit-dump-'.uniqid();

        $this->artisan("db:backup --name={$name} --threads=2")->assertSuccessful();

        Process::assertRan(function ($process) use ($name) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;

            return str_contains($cmd, 'mysqlsh')
                && str_contains($cmd, '--host=db.example')
                && str_contains($cmd, '--user=tester')
                && str_contains($cmd, 'util')
                && str_contains($cmd, 'dump-schemas')
                && str_contains($cmd, 'edcs_test')
                && str_contains($cmd, '--threads=2')
                && str_contains($cmd, "database/dumps/{$name}");
        });
    }

    public function test_fails_when_mysqlsh_returns_nonzero(): void
    {
        Process::fake([
            '*mysqlsh*' => Process::result(output: 'boom', exitCode: 1),
        ]);

        $this->artisan('db:backup --name=unit-dump-failing-'.uniqid())
            ->expectsOutputToContain('mysqlsh dump failed.')
            ->assertFailed();
    }
}
