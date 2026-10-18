<?php

namespace App\Console\Commands;

use App\Services\LocalBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class LocalBackupCommand extends Command
{
    protected $signature = 'summit:backup {--password-file= : Separate recovery password file, never included in backup} {--dump-binary=mysqldump : Local database dump client}';

    protected $description = 'Briefly pause web/queue work and create an AES-256 encrypted local database/key/originals snapshot';

    public function handle(LocalBackup $backup): int
    {
        $wasDown = app()->isDownForMaintenance();
        $enteredMaintenance = false;
        try {
            $password = $backup->password((string) $this->option('password-file'));
            if (! $wasDown) {
                Artisan::call('down');
                $enteredMaintenance = true;
            }
            $archive = $backup->create($password, $this->option('dump-binary'));
            $this->info('Encrypted backup created: '.$archive);

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Backup did not finish. Check the separate password file, dump client, queued imports and private storage. Secrets are not printed.');

            return self::FAILURE;
        } finally {
            if ($enteredMaintenance) {
                Artisan::call('up');
            }
        }
    }
}
