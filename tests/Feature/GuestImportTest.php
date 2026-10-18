<?php

namespace Tests\Feature;

use App\Jobs\CommitGuestImport;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\GuestRecord;
use App\Models\Permission;
use App\Models\User;
use App\Services\GuestImport;
use App\Services\GuestWorkbook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GuestImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 2));
    }

    private function csv(array $rows, bool $reverse = false): UploadedFile
    {
        $headers = $reverse ? array_reverse(GuestWorkbook::CSV_HEADERS) : GuestWorkbook::CSV_HEADERS;
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            $data = [...array_fill_keys(GuestWorkbook::CSV_HEADERS, ''), 'Event Date' => '2026-10-31', 'Event Session' => 'Morning',
                'Full Name' => 'Guest Import', 'Phone' => '08012345678', ...$row];
            fputcsv($stream, array_map(fn ($header) => $data[$header], $headers), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('guests.csv', $content);
    }

    private function preview(User $actor, UploadedFile $file, ?int $eventId = null): GuestImportBatch
    {
        $importer = app(GuestImport::class);
        $batch = $importer->upload($actor, $file, $eventId ?? ConferenceEvent::where('year', 2026)->firstOrFail()->id);
        $map = [];
        foreach ($batch->summary_json['groups'] as $key => $group) {
            $map[$key] = $group['suggested_session'] ?? ConferenceSession::where('conference_event_id', $batch->conference_event_id)->firstOrFail()->id;
        }
        $importer->preview($actor, $batch, array_column(array_filter($batch->summary_json['sheets'], fn ($sheet) => $sheet['rows'] > 0), 'name'), $map, 'Africa/Lagos');

        return $batch->fresh();
    }

    private function historic(): ConferenceSession
    {
        $event = ConferenceEvent::create(['name' => 'SUMMIT 2025', 'year' => 2025, 'starts_on' => '2025-10-28', 'ends_on' => '2025-10-28', 'timezone' => 'Africa/Lagos']);

        return ConferenceSession::create(['conference_event_id' => $event->id, 'code' => '2025-10-28-GENERAL', 'day_number' => 1,
            'date_local' => '2025-10-28', 'name' => 'General', 'label' => 'Day 1 — General']);
    }

    private function workbook(array $sheets, array $extraParts = []): UploadedFile
    {
        $temporary = tempnam(sys_get_temp_dir(), 'guest-xlsx-');
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::OVERWRITE);
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $workbook = '<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $relationships = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $workbook .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $relationships .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $xml = '<worksheet xmlns="'.$ns.'"><sheetData>';
            foreach ($rows as $number => $values) {
                $xml .= '<row r="'.($number + 1).'">';
                foreach (array_values($values) as $col => $value) {
                    $ref = chr(65 + $col).($number + 1);
                    if (is_array($value)) {
                        $xml .= '<c r="'.$ref.'"><f>SUM(1)</f><v>#NAME?</v></c>';
                    } elseif (is_int($value) || is_float($value)) {
                        $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                    } else {
                        $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
                    }
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet'.$i.'.xml', $xml.'</sheetData></worksheet>');
        }
        $zip->addFromString('xl/workbook.xml', $workbook.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relationships.'</Relationships>');
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        foreach ($extraParts as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();
        $content = file_get_contents($temporary);
        unlink($temporary);

        return UploadedFile::fake()->createWithContent('historical.xlsx', $content);
    }

    public function test_upload_mapping_and_preview_are_dry_runs_and_original_is_encrypted(): void
    {
        $actor = $this->userWithRole('super_admin');
        $file = $this->csv([[]], true);
        $this->actingAs($actor)->post(route('guest-imports.store'), ['file' => $file, 'event_id' => ConferenceEvent::first()->id])->assertSessionHasNoErrors()->assertRedirect();
        $batch = GuestImportBatch::firstOrFail();
        $this->assertSame('uploaded', $batch->status);
        $encrypted = Storage::disk('local')->get($batch->private_path);
        $this->assertStringNotContainsString('Guest Import', $encrypted);
        $this->assertStringContainsString('Guest Import', Crypt::decryptString($encrypted));
        $this->get(route('guest-imports.show', $batch))->assertOk()->assertSee('Select worksheets');
        $map = [];
        foreach ($batch->summary_json['groups'] as $key => $group) {
            $map[$key] = $group['suggested_session'];
        }
        $this->post(route('guest-imports.preview', $batch), ['sheets' => ['CSV'], 'session_map' => $map, 'source_timezone' => 'Africa/Lagos'])->assertSessionHasNoErrors();
        $this->get(route('guest-imports.show', $batch))->assertOk()->assertSee('Guest Import');
        $this->assertSame('previewed', $batch->fresh()->status);
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_csv_commit_and_repeat_are_idempotent_and_independent(): void
    {
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->csv([['Home Address' => 'Keep address', 'Heard About' => 'WhatsApp', 'Comment' => 'Keep note']], true));
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertSame('completed', $batch->fresh()->status);
        $guest = GuestRecord::firstOrFail();
        $this->assertSame('Keep address', $guest->home_address);
        $this->assertSame('Keep note', $guest->entry_notes);
        $this->assertSame('NOT CONTACTED', $guest->current_status);
        $this->assertSame('2026-10-31-AM', $guest->session->code);
        $repeat = $this->preview($actor, $this->csv([['Home Address' => 'Keep address', 'Heard About' => 'WhatsApp', 'Comment' => 'Keep note']]));
        $this->assertSame('duplicate', $repeat->rows()->first()->classification);
        app(GuestImport::class)->commit($actor, $repeat);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('guest_records', 1);
        $this->assertDatabaseCount('info_attendees', 0);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertDatabaseCount('follow_up_assignments', 0);
    }

    public function test_same_phone_needs_review_and_can_stay_a_separate_encounter(): void
    {
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->csv([[], ['Full Name' => 'Another guest']]));
        $this->assertSame(['new', 'review'], $batch->rows()->orderBy('id')->pluck('classification')->all());
        $this->actingAs($actor)->post(route('guest-imports.commit', $batch), ['confirm' => 1])->assertSessionHasErrors('import');
        app(GuestImport::class)->resolve($actor, $batch, $batch->rows()->orderByDesc('id')->first(), 'new');
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('guest_records', 2);
    }

    public function test_existing_encounter_link_preserves_profile_status_and_provenance(): void
    {
        $actor = $this->userWithRole('super_admin');
        $guest = GuestRecord::factory()->create(['conference_session_id' => ConferenceSession::where('code', '2026-10-31-AM')->first()->id,
            'full_name' => 'Trusted name', 'phone_normalized' => '+2348012345678']);
        $guest->forceFill(['current_status' => 'CONTACTED'])->save();
        $batch = $this->preview($actor, $this->csv([[]]));
        app(GuestImport::class)->resolve($actor, $batch, $batch->rows()->first(), 'match', $guest->id);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertSame(1, $batch->fresh()->rows_matched);
        $this->assertSame('Trusted name', $guest->fresh()->full_name);
        $this->assertSame('CONTACTED', $guest->fresh()->current_status);
        $this->assertDatabaseCount('guest_records', 1);
        $again = $this->preview($actor, $this->csv([[]]));
        $this->assertSame('duplicate', $again->rows()->first()->classification);
    }

    public function test_match_to_a_different_session_is_rejected(): void
    {
        $actor = $this->userWithRole('super_admin');
        $guest = GuestRecord::factory()->create(['phone_normalized' => '+2348012345678']);
        $batch = $this->preview($actor, $this->csv([[]]));
        $this->actingAs($actor)->post(route('guest-imports.resolve', [$batch, $batch->rows()->first()]),
            ['decision' => 'match', 'guest_id' => $guest->id])->assertSessionHasErrors('guest_id');
    }

    public function test_real_xlsx_handles_reordered_aliases_empty_sheet_numeric_dates_and_conflicts(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = $this->historic();
        $file = $this->workbook([
            'First tab' => [['PHONE', 'NAME', 'EVENTS', 'DATE', 'FOLLOW UP OFFICER', 'FOLLOW UP STATUS', 'COMMENT'],
                ['08012345678', 'Legacy person', 'Actual day one', 45958.5, 'Unknown officer', 'CONTACTED', 'Not picking']],
            'Sheet2' => [],
            'Second tab' => [['NAME', 'PHONE', 'EVENT', 'Timestamp'], ['Legacy person', '08012345678', 'Actual day one', '2025-10-28 13:00']],
        ]);
        $batch = $this->preview($actor, $file, $session->conference_event_id);
        $this->assertSame(2, $batch->rows_total);
        $this->assertSame(['review', 'review'], $batch->rows()->orderBy('id')->pluck('classification')->all());
        foreach ($batch->rows as $row) {
            app(GuestImport::class)->resolve($actor, $batch, $row, 'new');
        }
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('guest_records', 2);
        $guest = GuestRecord::orderBy('id')->firstOrFail();
        $this->assertSame('CONTACTED', $guest->raw_source_json['FOLLOW UP STATUS']);
        $this->assertSame('Unknown officer', $guest->legacy_officer_name);
        $this->assertSame('NOT CONTACTED', $guest->current_status);
        $this->assertSame('Not picking', $guest->entry_notes);
        $this->assertSame($session->id, $guest->conference_session_id);
        $this->assertSame('2025-10-28 12:00:00', GuestRecord::orderByDesc('id')->first()->captured_at_original_utc->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('follow_up_assignments', 0);
        $this->assertDatabaseCount('follow_up_activities', 0);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->actingAs($actor)->get(route('guests.index'))->assertDontSee('Legacy person');
        $this->get(route('guests.index', ['year' => 2025]))->assertSee('Legacy person');
        $repeat = $this->preview($actor, $file, $session->conference_event_id);
        $this->assertSame(2, $repeat->rows()->where('classification', 'duplicate')->count());
    }

    public function test_formula_error_names_and_formula_cells_are_quarantined(): void
    {
        $session = $this->historic();
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->workbook(['Bad rows' => [['NAME', 'PHONE'],
            ['#NAME?', '08012345678'], [['formula'], '08012345679'], ['=HYPERLINK("x")', '08012345670']]]), $session->conference_event_id);
        $this->assertSame(3, $batch->rows_invalid);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('guest_records', 0);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_invalid_csv_fields_and_session_mapping_are_reported(): void
    {
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->csv([['Phone' => '=SUM(1)'], ['Full Name' => ''], ['Event Date' => '2026-10-30']]));
        $this->assertSame(3, $batch->rows_invalid);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('guest_records', 0);
        $this->actingAs($actor)->get(route('guest-imports.results', $batch))->assertDownload()->assertOk();
    }

    public function test_queue_chunks_are_atomic_retryable_and_duplicate_delivery_safe(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $rows = [];
        for ($i = 0; $i < 105; $i++) {
            $rows[] = ['Full Name' => 'Guest '.$i, 'Phone' => '080'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)];
        }
        $batch = $this->preview($actor, $this->csv($rows));
        app(GuestImport::class)->commit($actor, $batch);
        Queue::assertPushedOn('imports', CommitGuestImport::class);
        $broken = $batch->rows()->orderBy('id')->skip(1)->first();
        $original = $broken->normalized_json;
        $broken->update(['normalized_json' => [...$original, 'conference_session_id' => 999999]]);
        $job = new CommitGuestImport($batch->id, $actor->id);
        $job->handle();
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertDatabaseCount('guest_records', 0);
        $broken->update(['normalized_json' => $original]);
        app(GuestImport::class)->commit($actor, $batch);
        $job->handle();
        $this->assertSame(100, $batch->fresh()->rows_created);
        $job->handle();
        $job->handle();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('guest_records', 105);
        $this->assertDatabaseCount('info_attendees', 0);
    }

    public function test_worker_rechecks_active_actor_and_permission(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->csv([[]]));
        app(GuestImport::class)->commit($actor, $batch);
        $actor->forceFill(['is_active' => false])->save();
        (new CommitGuestImport($batch->id, $actor->id))->handle();
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_only_explicit_import_permission_and_own_batches_are_available(): void
    {
        $entry = $this->userWithRole('data_entry');
        $admin = $this->userWithRole('super_admin');
        $batch = $this->preview($admin, $this->csv([[]]));
        $this->actingAs($entry)->get(route('guest-imports.index'))->assertForbidden();
        $entry->permissions()->attach(Permission::where('name', 'guest.import')->firstOrFail());
        $this->get(route('guest-imports.index'))->assertOk()->assertDontSee('guests.csv');
        $this->get(route('guest-imports.show', $batch))->assertForbidden();
        $this->get(route('guest-imports.results', $batch))->assertForbidden();
        $this->post(route('guest-imports.commit', $batch), ['confirm' => 1])->assertForbidden();
        $this->actingAs($this->userWithRole('officer'))->get(route('guest-imports.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('info_com'))->get(route('guest-imports.index'))->assertForbidden();
    }

    public function test_unconfirmed_commit_cross_batch_rows_and_processing_edits_are_rejected(): void
    {
        Queue::fake();
        $actor = $this->userWithRole('super_admin');
        $one = $this->preview($actor, $this->csv([[]]));
        $two = $this->preview($actor, $this->csv([['Full Name' => 'Another']]));
        $this->actingAs($actor)->post(route('guest-imports.commit', $one))->assertSessionHasErrors('confirm');
        $this->post(route('guest-imports.resolve', [$one, $two->rows()->first()]), ['decision' => 'skip'])->assertNotFound();
        app(GuestImport::class)->commit($actor, $one);
        $this->post(route('guest-imports.resolve', [$one, $one->rows()->first()]), ['decision' => 'skip'])->assertStatus(409);
    }

    public function test_manual_historical_configuration_preserves_authoritative_2026_sessions(): void
    {
        $actor = $this->userWithRole('super_admin');
        $this->actingAs($actor)->post(route('guest-imports.historical-session'),
            ['year' => 2025, 'date' => '2025-10-28', 'day_number' => 1, 'name' => 'General'])->assertSessionHasNoErrors();
        $session = ConferenceSession::where('code', '2025-10-28-GENERAL')->firstOrFail();
        $this->assertFalse($session->enabled);
        $this->assertNull($session->starts_at_utc);
        $this->assertSame(8, ConferenceSession::currentConference()->count());
        $this->post(route('guest-imports.historical-session'),
            ['year' => 2025, 'date' => '2026-10-28', 'day_number' => 1, 'name' => 'General'])->assertSessionHasErrors('date');
        $this->actingAs($this->userWithRole('follow_up_admin'))->post(route('guest-imports.historical-session'),
            ['year' => 2025, 'date' => '2025-10-28', 'day_number' => 1, 'name' => 'General'])->assertForbidden();
    }

    public function test_unsafe_and_malformed_workbooks_are_rejected_without_persisting_uploads(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = $this->historic();
        foreach ([
            $this->workbook(['Data' => [['NAME', 'PHONE'], ['Guest', '08012345678']]], ['xl/vbaProject.bin' => 'macro']),
            $this->workbook(['Data' => [['NAME', 'PHONE'], ['Guest', '08012345678']]], ['xl/workbook.xml' => '<!DOCTYPE x [<!ENTITY test SYSTEM "file:///etc/passwd">]><x/>']),
            UploadedFile::fake()->createWithContent('broken.xlsx', 'not a workbook'),
            $this->workbook(['Bad headers' => [['OTHER', 'PHONE'], ['Guest', '08012345678']]]),
        ] as $file) {
            $this->actingAs($actor)->post(route('guest-imports.store'), ['event_id' => $session->conference_event_id, 'file' => $file])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('guest_import_batches', 0);
    }

    public function test_mapping_to_wrong_year_or_without_selection_is_rejected(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = $this->historic();
        $batch = app(GuestImport::class)->upload($actor, $this->workbook(['Data' => [['NAME', 'PHONE'], ['Guest', '08012345678']]]), $session->conference_event_id);
        $key = array_key_first($batch->summary_json['groups']);
        $this->actingAs($actor)->post(route('guest-imports.preview', $batch),
            ['sheets' => ['Data'], 'session_map' => [$key => ConferenceSession::currentConference()->first()->id], 'source_timezone' => 'Africa/Lagos'])->assertSessionHasErrors('session_map');
        $this->post(route('guest-imports.preview', $batch), ['sheets' => [], 'session_map' => [], 'source_timezone' => 'UTC'])->assertSessionHasErrors();
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_shared_strings_and_rich_text_are_read_without_evaluating_values(): void
    {
        $session = $this->historic();
        $actor = $this->userWithRole('super_admin');
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $file = $this->workbook(['Data' => []], [
            'xl/sharedStrings.xml' => '<sst xmlns="'.$ns.'"><si><t>NAME</t></si><si><t>PHONE</t></si><si><r><t>Shared </t></r><r><t>Guest</t></r></si><si><t>08012345678</t></si></sst>',
            'xl/worksheets/sheet1.xml' => '<worksheet xmlns="'.$ns.'"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c></row></sheetData></worksheet>',
        ]);
        $batch = $this->preview($actor, $file, $session->conference_event_id);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseHas('guest_records', ['full_name' => 'Shared Guest', 'phone_normalized' => '+2348012345678']);
    }

    public function test_explicitly_granted_data_entry_imports_stay_in_own_scope(): void
    {
        $actor = $this->userWithRole('data_entry');
        $actor->permissions()->attach(Permission::where('name', 'guest.import')->firstOrFail());
        $batch = $this->preview($actor, $this->csv([[]]));
        $this->actingAs($actor)->get(route('guest-imports.show', $batch))->assertOk();
        $this->post(route('guest-imports.commit', $batch), ['confirm' => 1])->assertSessionHasNoErrors();
        $guest = GuestRecord::firstOrFail();
        $this->assertSame($actor->id, $guest->created_by_user_id);
        $this->get(route('guests.show', $guest))->assertOk();
        $this->actingAs($this->userWithRole('data_entry'))->get(route('guests.show', $guest))->assertForbidden();
    }

    public function test_ambiguous_and_invalid_legacy_timestamps_are_quarantined(): void
    {
        $session = $this->historic();
        $actor = $this->userWithRole('super_admin');
        $batch = $this->preview($actor, $this->workbook(['Data' => [['NAME', 'PHONE', 'DATE'],
            ['Ambiguous', '08012345678', '10/11/2025 09:00'], ['Invalid', '08012345679', '2025-02-30 09:00'],
            ['Future', '08012345670', '2099-01-01T09:00:00Z']]]), $session->conference_event_id);
        $this->assertSame(3, $batch->rows_invalid);
        app(GuestImport::class)->commit($actor, $batch);
        $this->assertDatabaseCount('guest_records', 0);
    }
}
