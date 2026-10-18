<?php

namespace App\Console\Commands;

use App\Services\Operations;
use Illuminate\Console\Command;

class ImportMaintenance extends Command
{
    protected $signature = 'summit:imports:maintain {action : recover or prune} {--days= : Explicit retention age for completed originals} {--apply : Apply the reviewed operation; default is dry-run}';

    protected $description = 'Dry-run recovery of stalled imports or explicitly approved original-file retention';

    public function handle(Operations $operations): int
    {
        try {
            $rows = $operations->maintain($this->argument('action'), (int) $this->option('days'), $this->option('apply'));
            $this->table(['Domain', 'Batch ID', 'Action', 'Applied'], array_map(fn ($row) => [$row['domain'], $row['batch_id'], $row['action'], $row['applied'] ? 'yes' : 'no'], $rows));
            $this->info($this->option('apply') ? 'Reviewed operation finished.' : 'Dry-run only. No files or records changed.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Maintenance could not finish. Use recover, or prune with explicit --days; inspect permissions and private paths. No source data is logged.');

            return self::FAILURE;
        }
    }
}
