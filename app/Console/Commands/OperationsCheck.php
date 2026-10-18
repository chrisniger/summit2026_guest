<?php

namespace App\Console\Commands;

use App\Services\Operations;
use Illuminate\Console\Command;

class OperationsCheck extends Command
{
    protected $signature = 'summit:doctor {--production : Also require future production settings} {--json : Emit machine-readable checks}';

    protected $description = 'Check local app readiness without exposing configuration secrets or changing data';

    public function handle(Operations $operations): int
    {
        $checks = $operations->checks($this->option('production'));
        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Action / detail'], array_map(fn ($row) => array_values($row), $checks));
        }

        return collect($checks)->contains('status', 'fail') ? self::FAILURE : self::SUCCESS;
    }
}
