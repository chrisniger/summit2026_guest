<?php

namespace App\Console\Commands;

use App\Services\LocalBackup;
use Illuminate\Console\Command;

class VerifyLocalBackup extends Command
{
    protected $signature = 'summit:backup:verify {archive} {--password-file= : Separate recovery password file} {--mysql-binary=mysql : Local database client}';

    protected $description = 'Verify encrypted originals/key and restore only to a new summit_2026_restore_check database, then remove that owned rehearsal database';

    public function handle(LocalBackup $backup): int
    {
        try {
            $result = $backup->verify($this->argument('archive'), $backup->password((string) $this->option('password-file')), $this->option('mysql-binary'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Verification failed. Check archive/password/client and ensure the isolated rehearsal database does not already exist. The active database was not selected as a restore target.');

            return self::FAILURE;
        }
    }
}
