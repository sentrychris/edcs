<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--name= : Name of the dump folder. Defaults to a timestamp.}
        {--threads=4 : Number of parallel threads mysqlsh should use.}
        {--bytes-per-chunk=64M : Approximate size of each table chunk file.}
        {--connection= : Database connection name. Defaults to the application default.}';

    protected $description = 'Create a parallel-restorable MySQL Shell dump of the application database.';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connection}");

        if (! $config || ($config['driver'] ?? null) !== 'mysql') {
            $this->error("Connection '{$connection}' is not a MySQL connection.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: 'dump-'.now()->format('Y-m-d_His');
        $path = static::dumpPath($name);

        if (is_dir($path)) {
            $this->error("Dump directory already exists: {$path}");

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $threads = (int) $this->option('threads');
        $bytesPerChunk = (string) $this->option('bytes-per-chunk');
        $username = $config['backup_username'] ?: $config['username'];
        $password = $config['backup_password'] ?: $config['password'];

        $this->line("Dumping schema '{$config['database']}' to {$path}");
        $this->line("Threads: {$threads}, chunk size: {$bytesPerChunk}, user: {$username}");

        $command = [
            'mysqlsh',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$username,
            '--password='.$password,
            '--no-wizard',
            '--',
            'util',
            'dump-schemas',
            $config['database'],
            '--output-url='.$path,
            '--threads='.$threads,
            '--bytes-per-chunk='.$bytesPerChunk,
            '--compression=zstd',
            '--consistent=true',
        ];

        $result = Process::forever()->run(
            $command,
            function (string $type, string $buffer) {
                $this->getOutput()->write($buffer);
            },
        );

        if (! $result->successful()) {
            $this->error('mysqlsh dump failed.');

            return self::FAILURE;
        }

        $this->info("Dump complete: {$path}");

        return self::SUCCESS;
    }

    public static function dumpPath(string $name): string
    {
        return storage_path("database/dumps/{$name}");
    }
}
