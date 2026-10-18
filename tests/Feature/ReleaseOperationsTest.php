<?php

namespace Tests\Feature;

use App\Jobs\CommitGuestImport;
use App\Jobs\CommitInfoImport;
use App\Livewire\SessionManager;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\InfoImportBatch;
use App\Services\GuestImport;
use App\Services\InfoCsvImport;
use App\Services\LocalBackup;
use App\Services\Operations;
use App\Services\SessionSchedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ReleaseOperationsTest extends TestCase
{
    private function batch(string $domain, string $status, string $age, ?string $path = null): InfoImportBatch|GuestImportBatch
    {
        $model = $domain === 'info' ? InfoImportBatch::class : GuestImportBatch::class;
        $batch = $model::create(['conference_event_id' => ConferenceEvent::first()->id, 'filename' => 'synthetic.csv',
            'sha256' => hash('sha256', uniqid()), 'status' => $status, 'uploaded_by_user_id' => $this->userWithRole('super_admin')->id, 'private_path' => $path]);
        $batch->timestamps = false;
        $batch->updated_at = now()->modify($age);
        $batch->save();

        return $batch;
    }

    public function test_sensitive_pages_redirects_errors_and_csv_responses_are_not_cacheable(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin')->assertRedirect('/login')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($this->userWithRole('super_admin'))->get('/admin')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get(route('follow-up.reports.export', ['type' => 'status']))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($this->userWithRole('info_com'))->get('/admin/operations')->assertForbidden()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_operations_dashboard_is_super_admin_only_and_cli_redacts_configuration(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin)->get('/admin/operations')->assertOk()->assertSee('Local operations')->assertDontSee(config('app.key'), false);
        foreach (['info_com', 'data_entry', 'officer', 'follow_up_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/admin/operations')->assertForbidden();
        }
        $checks = app(Operations::class)->checks();
        $this->assertFalse(collect($checks)->contains('status', 'fail'));
        $this->assertStringNotContainsString(config('app.key'), json_encode($checks));
        $this->artisan('summit:doctor', ['--json' => true])->assertSuccessful();
    }

    public function test_production_checks_do_not_mislabel_local_setup_as_production_ready(): void
    {
        $this->userWithRole('super_admin');
        $checks = collect(app(Operations::class)->checks(true))->keyBy('check');
        $this->assertSame('fail', $checks['Production settings']['status']);
        $this->assertSame('fail', $checks['HTTPS and cookies']['status']);
        $this->artisan('summit:doctor', ['--production' => true])->assertFailed();
    }

    public function test_local_private_storage_is_not_web_served_and_queue_reservation_exceeds_job_timeout(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertTrue(config('filesystems.disks.local.throw'));
        $this->assertGreaterThan((new CommitGuestImport(1, 1))->timeout, config('queue.connections.database.retry_after'));
        $this->assertGreaterThan((new CommitInfoImport(1, 1))->timeout, config('queue.connections.database.retry_after'));
        $this->get('/storage/guest-imports/anything.enc')->assertNotFound();
    }

    public function test_stalled_import_recovery_is_dry_run_by_default_and_keeps_pending_rows(): void
    {
        $info = $this->batch('info', 'processing', '-11 minutes');
        $guest = $this->batch('guest', 'processing', '-11 minutes');
        $recent = $this->batch('guest', 'processing', '-2 minutes');
        $operations = app(Operations::class);
        $this->assertCount(2, $operations->maintain('recover', 0, false));
        $this->assertSame('processing', $info->fresh()->status);
        $this->assertCount(2, $operations->maintain('recover', 0, true));
        $this->assertSame('failed', $info->fresh()->status);
        $this->assertSame('failed', $guest->fresh()->status);
        $this->assertSame('processing', $recent->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'import.recover']);
        $this->artisan('summit:imports:maintain', ['action' => 'recover'])->assertSuccessful();
    }

    public function test_completed_original_retention_requires_explicit_age_and_preserves_database_provenance(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('guest-imports/synthetic.csv.enc', 'encrypted fixture');
        $batch = $this->batch('guest', 'completed', '-31 days', 'guest-imports/synthetic.csv.enc');
        $batch->rows()->create(['row_number' => 2, 'worksheet_name' => 'CSV', 'raw_json' => ['Name' => 'Synthetic'],
            'row_fingerprint' => hash('sha256', 'synthetic'), 'classification' => 'new', 'processing_state' => 'created']);
        $this->artisan('summit:imports:maintain', ['action' => 'prune'])->assertFailed();
        app(Operations::class)->maintain('prune', 30, false);
        Storage::disk('local')->assertExists('guest-imports/synthetic.csv.enc');
        app(Operations::class)->maintain('prune', 30, true);
        Storage::disk('local')->assertMissing('guest-imports/synthetic.csv.enc');
        $this->assertNull($batch->fresh()->private_path);
        $this->assertSame(1, $batch->rows()->count());
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_pruning_preserves_unfinished_originals_and_rejects_paths_outside_domain(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('guest-imports/pending.csv.enc', 'fixture');
        $pending = $this->batch('guest', 'previewed', '-31 days', 'guest-imports/pending.csv.enc');
        app(Operations::class)->maintain('prune', 30, true);
        Storage::disk('local')->assertExists('guest-imports/pending.csv.enc');
        $bad = $this->batch('guest', 'completed', '-31 days', 'backup-recovery-key.txt');
        $this->artisan('summit:imports:maintain', ['action' => 'prune', '--days' => 30, '--apply' => true])->assertFailed();
        $this->assertSame('backup-recovery-key.txt', $bad->fresh()->private_path);
    }

    public function test_delayed_job_failure_cannot_regress_a_completed_import(): void
    {
        $info = $this->batch('info', 'completed', '-1 minute');
        $guest = $this->batch('guest', 'completed', '-1 minute');
        (new CommitInfoImport($info->id, $info->uploaded_by_user_id))->failed(new \RuntimeException);
        (new CommitGuestImport($guest->id, $guest->uploaded_by_user_id))->failed(new \RuntimeException);
        $this->assertSame('completed', $info->fresh()->status);
        $this->assertSame('completed', $guest->fresh()->status);
    }

    public function test_session_lock_and_unlock_are_audited_admin_actions_and_preserve_clock_boundaries(): void
    {
        $admin = $this->userWithRole('super_admin');
        $session = ConferenceSession::firstOrFail();
        app(SessionSchedule::class)->configure($admin, $session, '09:00', '11:00', true);
        Livewire::actingAs($admin)->test(SessionManager::class)->call('lock', $session->id, true)->assertHasNoErrors();
        $this->assertNotNull($session->fresh()->attendance_locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.lock_changed']);
        Livewire::actingAs($admin)->test(SessionManager::class)->call('lock', $session->id, false)->assertHasNoErrors();
        $this->assertNull($session->fresh()->attendance_locked_at);
        $this->assertSame('08:00', $session->fresh()->starts_at_utc->format('H:i'));
        Livewire::actingAs($this->userWithRole('info_com'))->test(SessionManager::class)->assertForbidden();
    }

    public function test_failed_private_original_write_creates_no_info_or_guest_import(): void
    {
        $admin = $this->userWithRole('super_admin');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->twice()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->twice()->andReturn($disk);
        $info = implode(',', InfoCsvImport::HEADERS)."\n2026-10-18T00:00:00Z,Test,Name,08012345678,,,,,,,,,,,,,,\n";
        $guest = "Event Date,Event Session,Full Name,Phone,Gender,Home Address,Heard About,Comment\n2026-10-27,General,Synthetic,08012345678,,,,\n";
        foreach ([
            fn () => app(InfoCsvImport::class)->preview($admin, UploadedFile::fake()->createWithContent('info.csv', $info)),
            fn () => app(GuestImport::class)->upload($admin, UploadedFile::fake()->createWithContent('guest.csv', $guest), ConferenceEvent::first()->id),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected private-storage validation error.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('file', $error->errors());
            }
        }
        $this->assertDatabaseCount('info_import_batches', 0);
        $this->assertDatabaseCount('guest_import_batches', 0);
    }

    public function test_missing_recovery_password_and_live_restore_target_are_refused(): void
    {
        $this->artisan('summit:backup', ['--password-file' => storage_path('missing-password')])->assertFailed();
        $this->assertFalse(app()->isDownForMaintenance());
        config(['database.connections.mysql.database' => LocalBackup::RESTORE_DATABASE]);
        try {
            app(LocalBackup::class)->verify('nonexistent.zip', 'irrelevant', 'mysql');
            $this->fail('Expected live-target guard.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('active application connection', $error->getMessage());
        }
    }
}
