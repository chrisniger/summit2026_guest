<?php

namespace App\Livewire;

use App\Models\InfoAttendee;
use App\Services\InfoAttendance;
use App\Services\SessionSchedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckInDesk extends Component
{
    use WithPagination;

    public string $search = '';

    public string $message = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->message = '';
    }

    public function mark(int $attendeeId, int $sessionId): void
    {
        Gate::authorize('info.attendance.mark');
        $this->resetValidation();
        try {
            $result = app(InfoAttendance::class)->manual(auth()->user(), $attendeeId, $sessionId);
        } catch (HttpException $error) {
            if ($error->getStatusCode() !== 409) {
                throw $error;
            }
            $this->addError('check_in', $error->getMessage());

            return;
        }
        $this->message = $result['already_present'] ? 'Already present — no duplicate check-in.' : 'Present — check-in saved.';
        $this->search = '';
        $this->resetPage();
        $this->dispatch('check-in-saved');
    }

    public function useNumber(int $attendeeId): void
    {
        Gate::authorize('info.access');
        $attendee = InfoAttendee::find($attendeeId);
        if (! $attendee) {
            return;
        }
        $this->search = $attendee->phone_raw;
        $this->message = '';
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('info.access');
        $session = app(SessionSchedule::class)->activeAt(now());
        $search = mb_substr(trim($this->search), 0, 100);
        $attendees = InfoAttendee::query()->search($search)->with(['attendanceRecords' => fn ($q) => $q->whereNull('voided_at')->where('conference_session_id', $session?->id ?? 0)])
            ->orderBy('last_name')->orderBy('first_name')->paginate(12);
        $phoneSuggestions = mb_strlen($search) >= 2 && preg_match('/\d/', $search)
            ? InfoAttendee::query()->search($search)->orderBy('last_name')->orderBy('first_name')->limit(6)->get(['id', 'first_name', 'last_name', 'phone_raw'])
            : collect();

        return view('livewire.check-in-desk', compact('session', 'attendees', 'phoneSuggestions'));
    }
}
