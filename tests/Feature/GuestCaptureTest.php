<?php

namespace Tests\Feature;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\InfoAttendee;
use App\Models\User;
use App\Services\GuestCapture;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestCaptureTest extends TestCase
{
    public function test_guest_list_page_size_and_pagination_preserve_the_assignment_filter(): void
    {
        $actor = $this->userWithRole('follow_up_admin');
        GuestRecord::factory()->count(51)->create();
        $this->actingAs($actor)->get(route('guests.index', ['assignment' => 'unassigned']))
            ->assertOk()->assertViewHas('guests', fn ($guests) => $guests->perPage() === 25 && $guests->count() === 25);
        $this->get(route('guests.index', ['assignment' => 'unassigned', 'per_page' => 50]))
            ->assertOk()->assertSee('Guests per page')->assertSee('Showing 1–50 of 51')
            ->assertViewHas('guests', function ($guests): bool {
                parse_str(parse_url($guests->nextPageUrl(), PHP_URL_QUERY), $query);

                return $guests->count() === 50 && $query['assignment'] === 'unassigned' && $query['per_page'] === '50' && $query['page'] === '2';
            });
        $this->get(route('guests.index', ['assignment' => 'unassigned', 'per_page' => 50, 'page' => 2]))
            ->assertOk()->assertSee('Showing 51–51 of 51')
            ->assertViewHas('guests', fn ($guests) => $guests->count() === 1);
        $this->get(route('guests.index', ['per_page' => 200]))->assertOk()
            ->assertViewHas('guests', fn ($guests) => $guests->perPage() === 200 && $guests->count() === 51);
        $this->get(route('guests.index', ['per_page' => 10000]))->assertSessionHasErrors('per_page');
    }

    public function test_save_returns_to_a_blank_form_with_confirmation_and_retained_date(): void
    {
        $actor = $this->userWithRole('data_entry');
        $data = $this->data();
        $this->actingAs($actor)->post(route('guests.store'), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('guests.create'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, $data['full_name']) && str_contains($status, $data['phone_raw']));
        $this->get(route('guests.create'))->assertOk()
            ->assertSee('Guest saved: Mike Guest')
            ->assertSeeHtml('name="full_name" required maxlength="255" autocomplete="name" value=""')
            ->assertViewHas('selectedSession', $data['conference_session_id'])
            ->assertViewHas('recentEntries', fn ($entries) => $entries->count() === 1 && $entries->first()->full_name === 'Mike Guest');
    }

    public function test_recent_entries_show_only_the_operators_latest_three_and_survive_a_new_session(): void
    {
        $actor = $this->userWithRole('super_admin');
        $own = GuestRecord::factory()->count(4)->create(['created_by_user_id' => $actor->id]);
        GuestRecord::factory()->create(['created_by_user_id' => $this->userWithRole('data_entry')->id, 'full_name' => 'Other operator entry']);
        $expected = $own->reverse()->take(3)->pluck('id')->values()->all();
        $this->actingAs($actor)->get(route('guests.create'))->assertOk()
            ->assertViewHas('recentEntries', fn ($entries) => $entries->pluck('id')->all() === $expected)
            ->assertDontSee('Other operator entry');
        $this->flushSession();
        $this->actingAs($actor)->get(route('guests.create'))->assertOk()
            ->assertViewHas('recentEntries', fn ($entries) => $entries->pluck('id')->all() === $expected);
    }

    public function test_guest_form_saves_a_single_referral_and_restores_it_for_editing(): void
    {
        $actor = $this->userWithRole('data_entry');
        $this->actingAs($actor)->get(route('guests.create'))->assertOk()
            ->assertSeeHtml('for="conference_session_id">Date *')
            ->assertSeeHtml('<select id="gender" name="gender">')
            ->assertSee('Billboard')->assertDontSee('Actual encounter session')
            ->assertSeeHtml('type="radio" name="heard_about"')
            ->assertDontSeeHtml('name="heard_about[]"');
        $this->post(route('guests.store'), $this->data([
            'gender' => 'Female', 'home_address' => '12 Test Street',
            'heard_about' => 'Social Media',
        ]))->assertSessionHasNoErrors();
        $guest = GuestRecord::sole();
        $this->assertSame('Female', $guest->gender);
        $this->assertSame('12 Test Street', $guest->home_address);
        $this->assertSame('Social Media', $guest->heard_about);
        $this->get(route('guests.edit', $guest))->assertOk()
            ->assertSeeHtml('value="Social Media" checked')
            ->assertDontSeeHtml('value="Personal Invitation" checked')
            ->assertDontSeeHtml('value="Flyers" checked');
        $this->patch(route('guests.update', $guest), $this->data(['version' => 1, 'heard_about' => ['Social Media', 'Flyers']]))->assertSessionHasErrors('heard_about');
        $this->patch(route('guests.update', $guest), $this->data(['version' => 1, 'heard_about' => '']))->assertSessionHasNoErrors();
        $this->assertNull($guest->fresh()->heard_about);
    }

    private function data(array $extra = []): array
    {
        return [...['conference_session_id' => ConferenceSession::firstOrFail()->id, 'full_name' => 'Mike Guest',
            'phone_raw' => '08012345678', 'submission_id' => (string) Str::uuid()], ...$extra];
    }

    public function test_post_session_entry_retains_session_and_cannot_post_privileged_fields(): void
    {
        $actor = $this->userWithRole('data_entry');
        $this->travelTo(now()->setDate(2026, 11, 2));
        $session = ConferenceSession::where('code', '2026-10-31-AM')->firstOrFail();
        InfoAttendee::factory()->create(['phone_normalized' => '+2348012345678']);
        $this->actingAs($actor)->post(route('guests.store'), $this->data(['conference_session_id' => $session->id,
            'current_status' => 'CONTACTED', 'created_by_user_id' => 999999, 'import_row_id' => 999999, 'officer_user_id' => 999999, 'source_fingerprint' => 'forged']))->assertSessionHasNoErrors()->assertRedirect();
        $guest = GuestRecord::firstOrFail();
        $this->assertSame($session->id, $guest->conference_session_id);
        $this->assertSame($actor->id, $guest->created_by_user_id);
        $this->assertSame('NOT CONTACTED', $guest->current_status);
        $this->assertSame('+2348012345678', $guest->phone_normalized);
        $this->assertNull($guest->import_row_id);
        $this->assertDatabaseCount('follow_up_assignments', 0);
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertDatabaseHas('audit_logs', ['domain' => 'follow_up', 'action' => 'guest.created', 'actor_user_id' => $actor->id]);
    }

    public function test_batch_entry_retains_selection_and_replayed_submission_creates_once(): void
    {
        $actor = $this->userWithRole('data_entry');
        $data = $this->data(['add_another' => 1]);
        $this->actingAs($actor)->post(route('guests.store'), $data)->assertRedirect(route('guests.create'))->assertSessionHas('guest_entry_session', $data['conference_session_id']);
        $this->post(route('guests.store'), $data)->assertRedirect(route('guests.create'));
        $this->assertDatabaseCount('guest_records', 1);
        $this->get(route('guests.create'))->assertOk()->assertViewHas('selectedSession', $data['conference_session_id']);
    }

    public function test_duplicate_warning_requires_explicit_separate_encounter_and_preserves_input(): void
    {
        $actor = $this->userWithRole('data_entry');
        $existing = GuestRecord::factory()->create(['created_by_user_id' => $actor->id, 'phone_normalized' => '+2348012345678']);
        $data = $this->data();
        $this->actingAs($actor)->from(route('guests.create'))->post(route('guests.store'), $data)->assertSessionHasErrors('keep_separate')->assertSessionHasInput('full_name', 'Mike Guest');
        $this->get(route('guests.create'))->assertOk()->assertSee($existing->full_name);
        $this->post(route('guests.store'), [...$data, 'keep_separate' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guest_records', 2);
    }

    public function test_other_operators_private_candidates_are_not_disclosed(): void
    {
        $actor = $this->userWithRole('data_entry');
        GuestRecord::factory()->create(['created_by_user_id' => $this->userWithRole('data_entry')->id,
            'full_name' => 'Private name', 'phone_normalized' => '+2348012345678']);
        $this->actingAs($actor)->post(route('guests.store'), $this->data())->assertSessionHasNoErrors();
        $this->get(route('guests.index'))->assertDontSee('Private name');
        $this->assertDatabaseCount('guest_records', 2);
    }

    public function test_role_permissions_and_guessed_ids_are_enforced(): void
    {
        $entry = $this->userWithRole('data_entry');
        $other = GuestRecord::factory()->create(['created_by_user_id' => $this->userWithRole('data_entry')->id, 'full_name' => 'Hidden guest']);
        $this->actingAs($entry)->get(route('guests.index'))->assertOk()->assertDontSee('Hidden guest');
        $this->get(route('guests.show', $other))->assertForbidden();
        $this->get(route('guests.edit', $other))->assertForbidden();
        $this->patch(route('guests.update', $other), $this->data(['version' => 1]))->assertForbidden();
        $this->actingAs($this->userWithRole('info_com'))->get(route('guests.index'))->assertForbidden();
        $this->post(route('guests.store'), $this->data())->assertForbidden();
        $officer = $this->userWithRole('officer');
        $this->actingAs($officer)->get(route('guests.create'))->assertForbidden();
        $this->get(route('guests.show', $other))->assertForbidden();
        $this->assign($other, $officer);
        $this->get(route('guests.index'))->assertSee('Hidden guest');
        $this->get(route('guests.show', $other))->assertOk()->assertSee($other->session->label);
        $this->patch(route('guests.update', $other), $this->data(['version' => 1]))->assertForbidden();
        $other->activeAssignment->update(['unassigned_at' => now()]);
        $this->get(route('guests.show', $other))->assertForbidden();
    }

    private function assign(GuestRecord $guest, User $officer): void
    {
        FollowUpAssignment::create(['guest_record_id' => $guest->id, 'officer_user_id' => $officer->id,
            'assigned_by_user_id' => $this->userWithRole('follow_up_admin')->id, 'assigned_at' => now()]);
    }

    public function test_profile_edit_is_audited_and_does_not_change_status_or_creator(): void
    {
        $actor = $this->userWithRole('data_entry');
        $guest = GuestRecord::factory()->create(['created_by_user_id' => $actor->id]);
        $guest->forceFill(['current_status' => 'UNREACHABLE'])->save();
        $this->actingAs($actor)->patch(route('guests.update', $guest), $this->data(['version' => 1,
            'full_name' => 'Corrected name', 'current_status' => 'CONTACTED', 'created_by_user_id' => 999999]))->assertSessionHasNoErrors();
        $this->assertSame('Corrected name', $guest->fresh()->full_name);
        $this->assertSame('UNREACHABLE', $guest->fresh()->current_status);
        $this->assertSame($actor->id, $guest->fresh()->created_by_user_id);
        $this->assertSame(2, $guest->fresh()->version);
        $this->patch(route('guests.update', $guest), $this->data(['version' => 1]))->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.updated', 'actor_user_id' => $actor->id]);
    }

    public function test_required_session_phone_and_capture_time_are_validated(): void
    {
        $this->actingAs($this->userWithRole('data_entry'));
        foreach ([['conference_session_id' => ''], ['conference_session_id' => 999999], ['full_name' => ''],
            ['phone_raw' => '=SUM(1)'], ['captured_at_original_utc' => '2099-01-01T09:00:00+01:00'],
            ['captured_at_original_utc' => '2025-01-01 09:00']] as $invalid) {
            $this->post(route('guests.store'), $this->data($invalid))->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_year_session_status_assignment_archive_and_phone_filters(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $event = ConferenceEvent::create(['name' => 'SUMMIT 2025', 'year' => 2025, 'starts_on' => '2025-10-28', 'ends_on' => '2025-10-28']);
        $historic = ConferenceSession::create(['conference_event_id' => $event->id, 'code' => 'history', 'day_number' => 1, 'date_local' => '2025-10-28', 'name' => 'General', 'label' => 'Historical session']);
        $old = GuestRecord::factory()->create(['full_name' => 'Historical person', 'conference_session_id' => $historic->id]);
        $new = GuestRecord::factory()->create(['full_name' => 'Current person']);
        $archived = GuestRecord::factory()->create(['full_name' => 'Archived person']);
        $archived->forceFill(['archived_at' => now()])->save();
        $this->actingAs($admin)->get(route('guests.index'))->assertSee('Current person')->assertDontSee('Historical person')->assertDontSee('Archived person');
        $this->get(route('guests.index', ['year' => 2025]))->assertSee('Historical person')->assertDontSee('Current person');
        $this->get(route('guests.index', ['search' => '+2348000000000']))->assertSee('Current person');
        $this->get(route('guests.index', ['search' => '08000000000']))->assertSee('Current person');
        $this->get(route('guests.index', ['session' => $historic->id]))->assertDontSee('Current person')->assertDontSee('Historical person');
        $this->get(route('guests.index', ['status' => 'CONTACTED']))->assertDontSee('Current person');
        $this->get(route('guests.index', ['assignment' => 'assigned']))->assertDontSee('Current person');
        $this->get(route('guests.index', ['archive' => 'archived']))->assertSee('Archived person')->assertDontSee('Current person');
        $this->get(route('guests.index', ['day' => 6]))->assertDontSee('Current person');
    }

    public function test_all_eight_disabled_sessions_are_valid_for_late_capture(): void
    {
        $actor = $this->userWithRole('data_entry');
        foreach (ConferenceSession::all() as $session) {
            $guest = app(GuestCapture::class)->create($actor, $this->data(['conference_session_id' => $session->id]), (string) Str::uuid(), true);
            $this->assertSame($session->id, $guest->conference_session_id);
        }
        $this->assertDatabaseCount('guest_records', 8);
        $this->assertDatabaseCount('info_attendees', 0);
    }

    public function test_capture_time_keeps_actual_utc_instant(): void
    {
        $this->actingAs($this->userWithRole('data_entry'))->post(route('guests.store'),
            $this->data(['captured_at_original_utc' => '2025-10-31T00:30:00+01:00']))->assertSessionHasNoErrors();
        $this->assertSame('2025-10-30 23:30:00', GuestRecord::firstOrFail()->captured_at_original_utc->format('Y-m-d H:i:s'));
    }
}
