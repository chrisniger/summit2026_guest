<?php

namespace Tests\Feature;

use App\Livewire\SessionManager;
use App\Models\ConferenceSession;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\ConferenceSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SessionScheduleTest extends TestCase
{
    public function test_unconfigured_sessions_are_not_active(): void
    {
        $this->assertNull(app(SessionSchedule::class)->activeAt(CarbonImmutable::parse('2026-10-31 10:00', 'Africa/Lagos')));
    }

    public function test_all_eight_sessions_resolve_with_correct_wat_conversion_and_exclusive_end(): void
    {
        $actor = $this->userWithRole('super_admin');
        $schedule = app(SessionSchedule::class);
        foreach (ConferenceSession::all() as $session) {
            $start = $session->name === 'Evening' ? '17:00' : '09:00';
            $end = $session->name === 'Evening' ? '19:00' : '12:00';
            $schedule->configure($actor, $session, $start, $end, true);
            $date = $session->date_local->format('Y-m-d');
            $instant = CarbonImmutable::parse($date.' '.$start, 'Africa/Lagos');
            $this->assertSame($session->id, $schedule->activeAt($instant)->id);
            $this->assertNull($schedule->activeAt($instant->subSecond()));
            $this->assertNull($schedule->activeAt(CarbonImmutable::parse($date.' '.$end, 'Africa/Lagos')));
            $this->assertSame($start === '17:00' ? '16:00' : '08:00', $session->fresh()->starts_at_utc->format('H:i'));
        }
    }

    public function test_overlapping_windows_are_rejected(): void
    {
        $actor = $this->userWithRole('super_admin');
        $sessions = ConferenceSession::whereDate('date_local', '2026-10-31')->get();
        $schedule = app(SessionSchedule::class);
        $schedule->configure($actor, $sessions[0], '09:00', '12:00', true);
        $this->expectException(ValidationException::class);
        $schedule->configure($actor, $sessions[1], '11:00', '13:00', true);
    }

    public function test_adjacent_windows_are_allowed_and_seed_does_not_reset_configuration(): void
    {
        $actor = $this->userWithRole('super_admin');
        $sessions = ConferenceSession::whereDate('date_local', '2026-10-31')->get();
        $schedule = app(SessionSchedule::class);
        $schedule->configure($actor, $sessions[0], '09:00', '12:00', true);
        $schedule->configure($actor, $sessions[1], '12:00', '14:00', true);
        $this->seed(ConferenceSeeder::class);
        $this->assertTrue($sessions[0]->fresh()->enabled);
        $this->assertSame($sessions[1]->id, $schedule->activeAt(CarbonImmutable::parse('2026-10-31 12:00', 'Africa/Lagos'))->id);
    }

    public function test_livewire_editor_saves_audited_schedule(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::first();
        Livewire::actingAs($actor)->test(SessionManager::class)
            ->call('edit', $session->id)->set('start', '09:00')->set('end', '11:00')->set('enabled', true)->call('save')->assertHasNoErrors()->assertSet('editing', null);
        $this->assertTrue($session->fresh()->enabled);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.configured', 'subject_id' => $session->id, 'actor_user_id' => $actor->id]);
    }

    public function test_livewire_rejects_half_configured_or_reversed_windows(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::first();
        Livewire::actingAs($actor)->test(SessionManager::class)->call('edit', $session->id)
            ->set('start', '09:00')->set('end', '')->set('enabled', true)->call('save')->assertHasErrors('schedule')
            ->set('end', '08:00')->call('save')->assertHasErrors('schedule');
        $this->assertFalse($session->fresh()->enabled);
    }

    public function test_locked_or_disabled_session_is_not_active(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::first();
        $schedule = app(SessionSchedule::class);
        $schedule->configure($actor, $session, '09:00', '11:00', true);
        $session->refresh()->update(['attendance_locked_at' => now()]);
        $this->assertNull($schedule->activeAt(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos')));
    }
}
