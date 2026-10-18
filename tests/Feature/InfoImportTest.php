<?php

namespace Tests\Feature;

use App\Jobs\CommitInfoImport;
use App\Models\ConferenceSession;
use App\Models\InfoAttendee;
use App\Models\InfoImportBatch;
use App\Services\InfoCsvImport;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfoImportTest extends TestCase
{
    public function test_original_utc_date_boundary_selects_local_session_and_gap_does_not(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::where('code', '2026-10-31-AM')->first();
        app(SessionSchedule::class)->configure($actor, $session, '00:00', '01:00', true);
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([
            $this->row(['Created' => '2026-10-30T23:30:00Z']),
            $this->row(['Created' => '2026-10-31T11:30:00Z', 'First Name' => 'Gap', 'Phone' => '08099999999']),
        ]));
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('info_attendees', 2);
        $this->assertDatabaseCount('info_attendance_records', 1);
        $this->assertDatabaseHas('info_attendance_records', ['conference_session_id' => $session->id, 'checked_in_at_utc' => '2026-10-30 23:30:00']);
    }

    public function test_reviewed_earlier_registration_updates_eligibility_without_overwriting_profile(): void
    {
        $actor = $this->userWithRole('super_admin');
        $attendee = InfoAttendee::factory()->create(['first_name' => 'Trusted', 'phone_normalized' => '+2348012345678', 'registration_original_at_utc' => '2026-11-01 08:00:00']);
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        app(InfoCsvImport::class)->resolve($actor, $batch, $batch->rows()->first(), 'match', $attendee->id);
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertSame('Trusted', $attendee->fresh()->first_name);
        $this->assertSame('2026-10-31 08:30:00', $attendee->fresh()->registration_original_at_utc->format('Y-m-d H:i:s'));
    }

    public function test_worker_rechecks_revoked_actor_permissions_before_writing(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        app(InfoCsvImport::class)->commit($actor, $batch);
        $actor->forceFill(['is_active' => false])->save();
        (new CommitInfoImport($batch->id, $actor->id))->handle();
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertDatabaseCount('info_attendees', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'Africa/Lagos'));
    }

    private function row(array $overrides = []): array
    {
        return array_replace(array_fill_keys(InfoCsvImport::HEADERS, ''), ['Created' => '2026-10-31T08:30:00.227Z', 'First Name' => 'Mike', 'Surname' => 'CSV Test', 'Phone' => '08012345678', 'Attendance' => 'In Person', 'Member' => 'No', 'Consent' => 'Yes'], $overrides);
    }

    private function file(array $rows, ?array $headers = null): UploadedFile
    {
        $headers ??= InfoCsvImport::HEADERS;
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($header) => $row[$header] ?? '', $headers), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('registration.csv', $content);
    }

    private function preview(array $rows): InfoImportBatch
    {
        return app(InfoCsvImport::class)->preview($this->userWithRole('super_admin'), $this->file($rows));
    }

    public function test_preview_is_a_dry_run_and_stores_encrypted_private_original(): void
    {
        $batch = $this->preview([$this->row()]);
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(1, $batch->rows_valid);
        $this->assertDatabaseCount('info_attendees', 0);
        $encrypted = Storage::disk('local')->get($batch->private_path);
        $this->assertStringNotContainsString('Mike', $encrypted);
        $this->assertStringContainsString('Mike', Crypt::decryptString($encrypted));
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_import_uses_original_timestamp_and_reupload_is_idempotent(): void
    {
        $actor = $this->userWithRole('super_admin');
        $morning = ConferenceSession::where('code', '2026-10-31-AM')->first();
        $evening = ConferenceSession::where('code', '2026-10-31-PM')->first();
        app(SessionSchedule::class)->configure($actor, $morning, '09:00', '12:00', true);
        app(SessionSchedule::class)->configure($actor, $evening, '17:00', '19:00', true);
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseHas('info_attendance_records', ['conference_session_id' => $morning->id, 'source' => 'csv_auto']);
        $this->assertDatabaseMissing('info_attendance_records', ['conference_session_id' => $evening->id]);
        $this->assertDatabaseHas('info_registration_events', ['registered_at_utc' => '2026-10-31 08:30:00']);
        $repeat = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        $this->assertSame('duplicate', $repeat->rows()->first()->classification);
        app(InfoCsvImport::class)->commit($actor, $repeat);
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_registration_events', 1);
        $this->assertDatabaseCount('info_attendance_records', 1);
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_reordered_headers_preserve_all_optional_fields_and_virtual_stays_registered(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::where('code', '2026-10-31-AM')->first();
        app(SessionSchedule::class)->configure($actor, $session, '09:00', '12:00', true);
        $row = $this->row(['Attendance' => 'Virtual', 'Email' => 'csv@example.test', 'Invite Count' => '2', 'Bus Shuttle' => 'Yes', 'Bus Route' => 'North', 'Feedback' => 'Retain this', 'Location' => 'Abuja', 'Abuja Area' => 'Central', 'Summit Centre' => 'Main', 'First Time' => '', 'Heard About' => 'WhatsApp', 'Commits to Invite' => 'No']);
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$row], array_reverse(InfoCsvImport::HEADERS)));
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertDatabaseHas('info_attendees', ['attendance_preference' => 'Virtual', 'invite_count' => 2, 'bus_shuttle' => true, 'bus_route' => 'North', 'feedback' => 'Retain this', 'first_time' => null, 'consent' => true]);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_ambiguous_same_phone_requires_explicit_review_without_silent_merge(): void
    {
        $actor = $this->userWithRole('super_admin');
        $attendee = InfoAttendee::factory()->create(['first_name' => 'Trusted', 'phone_normalized' => '+2348012345678']);
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        $row = $batch->rows()->first();
        $this->assertSame('review', $row->classification);
        $this->actingAs($actor)->post('/info-com/imports/'.$batch->id.'/commit', ['confirm' => 1])->assertSessionHasErrors('import');
        $this->post('/info-com/imports/'.$batch->id.'/rows/'.$row->id, ['decision' => 'match', 'attendee_id' => $attendee->id])->assertSessionHasNoErrors();
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertSame('Trusted', $attendee->fresh()->first_name);
        $this->assertSame(1, $batch->fresh()->rows_matched);
    }

    public function test_shared_phone_in_file_can_be_kept_as_separate_people_and_duplicates_skip(): void
    {
        $actor = $this->userWithRole('super_admin');
        $first = $this->row();
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$first, $first, $this->row(['First Name' => 'Jane'])]));
        $rows = $batch->rows()->orderBy('row_number')->get();
        $this->assertSame(['new', 'duplicate', 'review'], $rows->pluck('classification')->all());
        app(InfoCsvImport::class)->resolve($actor, $batch, $rows[2], 'new', null);
        app(InfoCsvImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('info_attendees', 2);
        $this->assertSame(1, $batch->fresh()->rows_skipped);
    }

    public function test_invalid_timestamps_booleans_and_formula_like_phone_are_quarantined(): void
    {
        $batch = $this->preview([$this->row(['Created' => '2026-10-31 10:00']), $this->row(['Created' => '2026-02-30T10:00:00Z']), $this->row(['Member' => 'maybe']), $this->row(['Phone' => '=SUM(1)']), $this->row(['Created' => '2025-10-31T08:00:00Z'])]);
        $this->assertSame(5, $batch->rows_invalid);
        app(InfoCsvImport::class)->commit($this->userWithRole('super_admin'), $batch);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('info_attendees', 0);
    }

    public function test_queued_chunks_commit_once_even_with_duplicate_job_delivery(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $rows = [];
        for ($i = 0; $i < 105; $i++) {
            $rows[] = $this->row(['First Name' => 'Person'.$i, 'Surname' => 'Batch'.$i, 'Phone' => '+447'.str_pad((string) $i, 9, '0', STR_PAD_LEFT)]);
        }
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file($rows));
        app(InfoCsvImport::class)->commit($actor, $batch);
        Queue::assertPushedOn('imports', CommitInfoImport::class);
        $job = new CommitInfoImport($batch->id, $actor->id);
        $job->handle();
        $this->assertSame(100, $batch->fresh()->rows_created);
        $job->handle();
        $job->handle();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('info_attendees', 105);
        $this->assertDatabaseCount('info_registration_events', 105);
    }

    public function test_failed_chunk_rolls_back_and_retry_skips_already_completed_rows(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $batch = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row(), $this->row(['First Name' => 'Jane', 'Phone' => '08099999999'])]));
        app(InfoCsvImport::class)->commit($actor, $batch);
        $broken = $batch->rows()->orderByDesc('id')->first();
        $original = $broken->normalized_json;
        $broken->update(['normalized_json' => array_merge($original, ['_original_at' => 'invalid timestamp'])]);
        (new CommitInfoImport($batch->id, $actor->id))->handle();
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertDatabaseCount('info_attendees', 0);
        $broken->update(['normalized_json' => $original]);
        app(InfoCsvImport::class)->commit($actor, $batch);
        (new CommitInfoImport($batch->id, $actor->id))->handle();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('info_attendees', 2);
    }

    public function test_unauthorized_imports_and_row_from_another_batch_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('info_com'))->get('/info-com/imports')->assertForbidden();
        $this->post('/info-com/imports', ['file' => $this->file([$this->row()])])->assertForbidden();
        $actor = $this->userWithRole('super_admin');
        $one = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row()]));
        $two = app(InfoCsvImport::class)->preview($actor, $this->file([$this->row(['First Name' => 'Other'])]));
        $this->actingAs($actor)->post('/info-com/imports/'.$one->id.'/rows/'.$two->rows()->first()->id, ['decision' => 'skip'])->assertNotFound();
        $this->get('/info-com/imports/'.$one->id)->assertOk();
        $this->get('/info-com/imports/'.$one->id.'/results')->assertOk()->assertDownload();
    }

    public function test_header_errors_and_unconfirmed_commit_are_rejected(): void
    {
        $actor = $this->userWithRole('super_admin');
        $this->actingAs($actor)->post('/info-com/imports', ['file' => UploadedFile::fake()->createWithContent('bad.csv', "Name,Phone\nMike,08012345678\n")])->assertSessionHasErrors('file');
        $batch = $this->preview([$this->row()]);
        $this->actingAs($actor)->post('/info-com/imports/'.$batch->id.'/commit', [])->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('info_attendees', 0);
    }
}
