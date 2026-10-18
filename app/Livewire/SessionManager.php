<?php

namespace App\Livewire;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Services\Audit;
use App\Services\SessionSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class SessionManager extends Component
{
    public ?int $editing = null;

    public string $start = '';

    public string $end = '';

    public bool $enabled = false;

    public function edit(int $id): void
    {
        Gate::authorize('platform.manage');
        $session = ConferenceSession::currentConference()->findOrFail($id);
        $this->editing = $session->id;
        $this->start = $session->starts_at_utc?->setTimezone(config('summit.timezone'))->format('H:i') ?? '';
        $this->end = $session->ends_at_utc?->setTimezone(config('summit.timezone'))->format('H:i') ?? '';
        $this->enabled = $session->enabled;
        $this->resetValidation();
    }

    public function save(SessionSchedule $schedule): void
    {
        Gate::authorize('platform.manage');
        $this->validate(['editing' => 'required|integer', 'start' => 'nullable|date_format:H:i', 'end' => 'nullable|date_format:H:i', 'enabled' => 'boolean']);
        $session = ConferenceSession::currentConference()->findOrFail($this->editing);
        $schedule->configure(auth()->user(), $session, $this->start ?: null, $this->end ?: null, $this->enabled);
        $this->editing = null;
        session()->flash('status', 'Session schedule saved.');
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetValidation();
    }

    public function lock(int $id, bool $locked): void
    {
        Gate::authorize('platform.manage');
        $session = ConferenceSession::currentConference()->findOrFail($id);
        DB::transaction(function () use ($session, $locked): void {
            ConferenceEvent::whereKey($session->conference_event_id)->lockForUpdate()->firstOrFail();
            $session = ConferenceSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $before = $session->only('attendance_locked_at');
            $session->update(['attendance_locked_at' => $locked ? now() : null]);
            app(Audit::class)->record('platform', 'session.lock_changed', $session, auth()->user(), $before, $session->only('attendance_locked_at'));
        });
        session()->flash('status', $locked ? 'Attendance locked for this session.' : 'Attendance lock removed. Scheduled boundaries still apply.');
    }

    public function render(): View
    {
        Gate::authorize('platform.manage');

        return view('livewire.session-manager', ['sessions' => ConferenceSession::currentConference()->orderBy('date_local')->orderBy('id')->get()]);
    }
}
