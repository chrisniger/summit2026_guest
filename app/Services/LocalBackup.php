<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class LocalBackup
{
    public const RESTORE_DATABASE = 'summit_2026_restore_check';

    // Verification reads entries in memory; bound snapshots for the local 128 MB PHP runtime.
    private const LIMIT = 33554432;

    public static function directory(string $database): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
            throw new \RuntimeException('Unsupported local backup database name.');
        }

        return storage_path('app/private/backups/'.$database);
    }

    public function password(string $path): string
    {
        if (! is_file($path) || filesize($path) > 4096) {
            throw new \RuntimeException('A separate recovery-password file is required.');
        }
        $password = trim(file_get_contents($path));
        if (strlen($password) < 32) {
            throw new \RuntimeException('Recovery password must contain at least 32 characters.');
        }

        return $password;
    }

    private function clientOptions(): string
    {
        if (config('database.default') !== 'mysql') {
            throw new \RuntimeException('The local backup contract requires MySQL/MariaDB.');
        }
        $config = config('database.connections.mysql');
        $escape = fn (string $value) => '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']).'"';
        $file = tempnam(storage_path('app/private'), 'db-client-');
        chmod($file, 0600);
        file_put_contents($file, "[client]\nhost=".$escape($config['host'])."\nport=".(int) $config['port']."\nuser=".$escape($config['username'])."\npassword=".$escape($config['password'] ?? '')."\n");

        return $file;
    }

    private function tables(): array
    {
        return ['users', 'roles', 'permissions', 'role_user', 'permission_role', 'permission_user', 'conference_events', 'conference_sessions',
            'info_attendees', 'info_registration_events', 'info_attendance_records', 'info_import_batches', 'info_import_rows',
            'guest_records', 'guest_import_batches', 'guest_import_rows', 'follow_up_assignments', 'follow_up_activities', 'follow_up_status_history', 'audit_logs', 'app_settings'];
    }

    private function counts(): array
    {
        return collect($this->tables())->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    private function usersHash(?string $database = null): string
    {
        return hash('sha256', DB::table($database ? $database.'.users' : 'users')->select('id', 'email', 'password', 'is_active')->orderBy('id')->get()->toJson());
    }

    public function create(string $password, string $binary): string
    {
        if (! app()->isDownForMaintenance() || strlen($password) < 32) {
            throw new \RuntimeException('Pause web operations in maintenance mode and supply a separate recovery password.');
        }
        if (DB::table('jobs')->where('queue', 'imports')->exists()
            || DB::table('info_import_batches')->where('status', 'processing')->exists()
            || DB::table('guest_import_batches')->where('status', 'processing')->exists()) {
            throw new \RuntimeException('Finish or safely recover outstanding import work before a coordinated backup.');
        }
        $options = $this->clientOptions();
        $sql = tempnam(storage_path('app/private'), 'db-dump-');
        $keyFile = tempnam(storage_path('app/private'), 'app-key-');
        $directory = self::directory(config('database.connections.mysql.database'));
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $archive = $directory.'/summit-'.now()->format('Ymd-His').'-'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        $opened = false;
        $completed = false;
        try {
            $counts = $this->counts();
            $usersHash = $this->usersHash();
            $process = new Process([$binary, '--defaults-extra-file='.$options, '--single-transaction', '--skip-lock-tables',
                '--hex-blob', '--skip-comments', '--default-character-set=utf8mb4', '--result-file='.$sql, config('database.connections.mysql.database')]);
            $process->setTimeout(300);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($sql) || filesize($sql) < 1) {
                throw new \RuntimeException('Database dump failed. Check the configured local client and database access.');
            }
            file_put_contents($keyFile, config('app.key'));
            $sources = ['database.sql' => $sql, 'app-key.txt' => $keyFile];
            foreach (['info-imports', 'guest-imports'] as $folder) {
                foreach (Storage::disk('local')->allFiles($folder) as $path) {
                    $sources['private/'.$path] = Storage::disk('local')->path($path);
                }
            }
            $size = 0;
            foreach ($sources as $source) {
                $size += filesize($source);
            }
            if ($size > self::LIMIT) {
                throw new \RuntimeException('Local snapshot exceeds the 32 MB contract; use an approved streaming backup system.');
            }
            if ($counts !== $this->counts() || $usersHash !== $this->usersHash()) {
                throw new \RuntimeException('Operational data changed during the dump. Stop writers and retry.');
            }
            $manifest = ['format' => 1, 'created_at' => now()->toIso8601String(), 'cipher' => config('app.cipher'),
                'database' => config('database.connections.mysql.database'), 'tables' => $counts, 'users_hash' => $usersHash,
                'entries' => array_map(fn ($source) => hash_file('sha256', $source), $sources)];
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true || ! $zip->setPassword($password)) {
                throw new \RuntimeException('Cannot create encrypted backup.');
            }
            $opened = true;
            foreach ($sources as $name => $source) {
                if (! $zip->addFile($source, $name) || ! $zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
                    throw new \RuntimeException('Backup entry encryption failed.');
                }
            }
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR))
                || ! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) {
                throw new \RuntimeException('Backup manifest encryption failed.');
            }
            $closed = $zip->close();
            $opened = false;
            if (! $closed) {
                throw new \RuntimeException('Encrypted archive could not be finalized.');
            }
            $completed = true;

            return $archive;
        } finally {
            if ($opened) {
                $zip->close();
            }
            foreach ([$options, $sql, $keyFile] as $temporary) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            if (! $completed && is_file($archive)) {
                unlink($archive);
            }
        }
    }

    public function verify(string $archive, string $password, string $binary): array
    {
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') === self::RESTORE_DATABASE) {
            throw new \RuntimeException('Refusing to use the verification database as the active application connection.');
        }
        if (! is_file($archive) || filesize($archive) > self::LIMIT) {
            throw new \RuntimeException('Backup is missing or exceeds the local verification limit.');
        }
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Invalid backup archive.');
        }
        $created = false;
        $options = null;
        try {
            $zip->setPassword($password);
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            if ($size > self::LIMIT) {
                throw new \RuntimeException('Expanded backup exceeds 32 MB.');
            }
            $manifestText = @$zip->getFromName('manifest.json');
            $manifest = $manifestText === false ? null : json_decode($manifestText, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || count($manifest['entries'] ?? []) + 1 !== $zip->numFiles) {
                throw new \RuntimeException('Backup password or manifest is invalid.');
            }
            foreach ($manifest['entries'] as $name => $hash) {
                $content = @$zip->getFromName($name);
                if ($content === false || ! hash_equals($hash, hash('sha256', $content))) {
                    throw new \RuntimeException('Backup entry failed integrity verification.');
                }
            }
            $key = $zip->getFromName('app-key.txt');
            $encrypter = new Encrypter(str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key, $manifest['cipher']);
            foreach (array_keys($manifest['entries']) as $name) {
                if (str_starts_with($name, 'private/') && str_ends_with($name, '.enc')) {
                    $encrypter->decryptString($zip->getFromName($name));
                }
            }
            $sql = $zip->getFromName('database.sql');
            if ($sql === false || preg_match('/^\s*(?:USE\s|(?:CREATE|DROP|ALTER)\s+DATABASE\b)/mi', $sql)) {
                throw new \RuntimeException('The verification dump must not switch or manage databases.');
            }
            if (DB::select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [self::RESTORE_DATABASE])) {
                throw new \RuntimeException('The isolated verification database already exists; inspect it before any manual cleanup.');
            }
            DB::statement('CREATE DATABASE '.self::RESTORE_DATABASE.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created = true;
            $options = $this->clientOptions();
            $process = new Process([$binary, '--defaults-extra-file='.$options, '--default-character-set=utf8mb4', self::RESTORE_DATABASE]);
            $process->setInput($sql);
            $process->setTimeout(300);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('Isolated database restoration failed.');
            }
            if (array_keys($manifest['tables']) !== $this->tables()) {
                throw new \RuntimeException('Backup schema manifest is incompatible.');
            }
            foreach ($manifest['tables'] as $table => $count) {
                if (DB::table(self::RESTORE_DATABASE.'.'.$table)->count() !== $count) {
                    throw new \RuntimeException('Restored row counts do not match the backup.');
                }
            }
            if (! hash_equals($manifest['users_hash'], $this->usersHash(self::RESTORE_DATABASE))) {
                throw new \RuntimeException('Restored staff identity/password hashes do not match.');
            }
            $result = ['archive' => basename($archive), 'source_database' => $manifest['database'], 'sha256' => hash_file('sha256', $archive), 'verified_at' => now()->toIso8601String(),
                'tables_checked' => count($manifest['tables']), 'originals_checked' => count($manifest['entries']) - 2];
            $receipt = self::directory($manifest['database']);
            if (! is_dir($receipt)) {
                mkdir($receipt, 0700, true);
            }
            file_put_contents($receipt.'/latest-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $result;
        } finally {
            $zip->close();
            try {
                if ($created) {
                    DB::statement('DROP DATABASE '.self::RESTORE_DATABASE);
                }
            } finally {
                if ($options && is_file($options)) {
                    unlink($options);
                }
            }
        }
    }
}
