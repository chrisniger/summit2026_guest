<?php

namespace App\Services;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\InfoImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Operations
{
    public function checks(bool $production = false): array
    {
        $checks = [];
        $add = function (string $name, bool $passes, string $message, bool $required = true) use (&$checks): void {
            $checks[] = ['check' => $name, 'status' => $passes ? 'ok' : ($required ? 'fail' : 'warning'), 'message' => $message];
        };
        $add('PHP extensions', version_compare(PHP_VERSION, '8.4.1', '>=') && collect(['pdo_mysql', 'zip', 'dom', 'mbstring', 'fileinfo', 'openssl'])->every(fn ($extension) => extension_loaded($extension)), 'PHP 8.4.1+ and MySQL/ZIP/XML/multibyte/fileinfo/OpenSSL are required.');
        $add('Application key', filled(config('app.key')), 'Existing application encryption key must be preserved.');
        $add('Built assets', is_file(public_path('build/manifest.json')), 'Run npm ci and npm run build after frontend changes.');
        $add('Private uploads', config('filesystems.disks.local.serve') === false && config('filesystems.disks.local.throw') === true, 'Uploads must stay private and write failures must interrupt imports.');
        $retry = (int) config('queue.connections.database.retry_after');
        $add('Queue reservation', $retry > 120, 'Database retry_after must exceed the 120-second import timeout.');
        try {
            DB::select('SELECT 1');
            $add('Database connection', true, 'Database connection works.');
            $pending = array_diff(array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))),
                DB::table('migrations')->pluck('migration')->all());
            $add('Migrations', ! $pending, 'All checked-in migrations must be applied.');
            $add('Super Admin', User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->exists(), 'At least one active Super Admin is required.');
            $add('Staff onboarding', User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('name', ['info_com', 'officer', 'data_entry']))->exists(), 'Provision actual staff before team operations.', false);
            $sessions = ConferenceSession::currentConference()->get();
            $add('Attendance schedule', $sessions->count() === 8 && $sessions->every(fn ($session) => $session->enabled && $session->starts_at_utc && $session->ends_at_utc), 'Enter verified WAT times and enable the eight sessions before attendance use.', false);
            $add('Paused imports', InfoImportBatch::where('status', 'failed')->count() + GuestImportBatch::where('status', 'failed')->count() === 0, 'Review failed imports and retry through their authorized import screens.', false);
            $stale = InfoImportBatch::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(10))->count()
                + GuestImportBatch::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(10))->count();
            $add('Stalled imports', $stale === 0, 'Use summit:imports:maintain recover --apply after reviewing stale imports.', false);
            $add('Failed queue jobs', DB::table('failed_jobs')->where('queue', 'imports')->count() === 0, 'Review failed imports jobs with queue:failed.', false);
            $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
            $add('Database lifecycle', ! str_contains($version, 'MariaDB') || version_compare($version, '10.11', '>='), 'XAMPP MariaDB 10.4 remains local-only; use a maintained database for a future production release.', $production);
        } catch (\Throwable) {
            $add('Database checks', false, 'Database or migrations are unavailable. Check local database service/configuration; no credentials are printed.');
        }
        try {
            $heartbeat = Cache::get('summit:imports-worker');
            $scheduler = Cache::get('summit:scheduler');
        } catch (\Throwable) {
            $heartbeat = $scheduler = null;
        }
        $add('Imports worker', is_int($heartbeat) && $heartbeat >= now()->subMinutes(3)->timestamp, 'Run the imports worker; heartbeat must be less than three minutes old.', false);
        $add('Scheduler', is_int($scheduler) && $scheduler >= now()->subMinutes(3)->timestamp, 'Run schedule:work locally or the scheduled production task; heartbeat must be recent.', false);
        $receipt = LocalBackup::directory(config('database.connections.mysql.database')).'/latest-verified.json';
        $fresh = is_file($receipt) && filemtime($receipt) >= now()->subHours(24)->timestamp;
        $add('Verified backup', $fresh, 'Run an encrypted backup and isolated restore check; protect the separate recovery password file.', false);
        if ($production) {
            $add('Production settings', app()->environment('production') && ! config('app.debug'), 'Production requires APP_ENV=production and APP_DEBUG=false.');
            $add('HTTPS and cookies', str_starts_with(config('app.url'), 'https://') && config('session.secure') === true && config('session.http_only') === true, 'HTTPS URL and secure HTTP-only session cookies are required.');
            $add('Database privilege', ! in_array(config('database.connections.mysql.username'), ['root', ''], true), 'Use a dedicated least-privilege database account.');
            $add('Mail delivery', ! in_array(config('mail.default'), ['log', 'array'], true), 'Configure and independently verify real mail delivery before password-reset emails are needed.');
        }

        return $checks;
    }

    public function maintain(string $action, int $days, bool $apply): array
    {
        if (! in_array($action, ['recover', 'prune'], true) || ($action === 'prune' && $days < 1)) {
            throw ValidationException::withMessages(['maintenance' => 'Choose recover, or prune with an explicit positive --days retention threshold.']);
        }
        $results = [];
        foreach (['info_com' => InfoImportBatch::class, 'follow_up' => GuestImportBatch::class] as $domain => $model) {
            $query = $model::query()->where('status', $action === 'recover' ? 'processing' : 'completed')
                ->where('updated_at', '<', $action === 'recover' ? now()->subMinutes(10) : now()->subDays($days));
            if ($action === 'prune') {
                $query->whereNotNull('private_path');
            }
            foreach ($query->orderBy('id')->get() as $candidate) {
                $changed = false;
                if ($apply) {
                    $changed = DB::transaction(function () use ($candidate, $domain, $action, $days): bool {
                        ConferenceEvent::whereKey($candidate->conference_event_id)->lockForUpdate()->firstOrFail();
                        $batch = $candidate::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                        $cutoff = $action === 'recover' ? now()->subMinutes(10) : now()->subDays($days);
                        if ($batch->status !== ($action === 'recover' ? 'processing' : 'completed') || $batch->updated_at->gte($cutoff)) {
                            return false;
                        }
                        if ($action === 'recover') {
                            $batch->update(['status' => 'failed', 'failure_message' => 'Worker progress stopped for over ten minutes. Review and retry pending rows safely.']);
                        } else {
                            $prefix = $domain === 'info_com' ? 'info-imports/' : 'guest-imports/';
                            if (! $batch->private_path || ! str_starts_with($batch->private_path, $prefix) || str_contains($batch->private_path, '..') || str_contains($batch->private_path, '\\')) {
                                throw new \RuntimeException('Refusing an invalid private-original path.');
                            }
                            if (! Storage::disk('local')->delete($batch->private_path)) {
                                throw new \RuntimeException('The original could not be removed; the stored reference is preserved.');
                            }
                            $batch->update(['private_path' => null]);
                        }
                        app(Audit::class)->record($domain, 'import.'.$action, $batch, null, [], ['source' => 'operator_cli']);

                        return true;
                    }, 3);
                }
                $results[] = ['domain' => $domain, 'batch_id' => $candidate->id, 'action' => $action, 'applied' => $changed];
            }
        }

        return $results;
    }
}
