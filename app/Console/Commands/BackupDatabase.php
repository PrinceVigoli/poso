<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'poso:backup';
    protected $description = 'Create a consistent MySQL backup in private application storage';

    public function handle(): int
    {
        $name = config('database.default');
        $db = config('database.connections.'.$name);
        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error('This backup command requires the configured MySQL connection.');
            return self::FAILURE;
        }
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700);
        $file = $directory.'/poso-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
        $credentials = tempnam($directory, 'mysql-options-');
        $quote = fn ($value) => '"'.str_replace(["\\", '"', "\r", "\n"], ['\\\\', '\\"', '\\r', '\\n'], (string)$value).'"';
        file_put_contents($credentials, "[client]\nhost=".$quote($db['host'])."\nport=".(int)$db['port']."\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\n");
        @chmod($credentials, 0600);
        try {
            $process = new Process([config('backups.mysqldump'), '--defaults-extra-file='.$credentials, '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--result-file='.$file, $db['database']]);
            $process->setTimeout(600);
            $process->mustRun();
            $this->info('Backup saved in private storage: '.basename($file));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // An incomplete artifact is never advertised as a backup.
            if (is_file($file)) { unlink($file); }
            $this->error('Database backup failed. Check mysqldump configuration and database availability.');
            report($e);
            return self::FAILURE;
        } finally {
            if (is_file($credentials)) { unlink($credentials); }
        }
    }
}
