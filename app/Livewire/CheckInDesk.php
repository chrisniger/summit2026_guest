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

    public ?int $selected = null;

    public string $message = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selected = null;
        $this->message = '';
    }

    public function selectAttendee(int $id): void
    {
        Gate::authorize('info.access');
        InfoAttendee::findOrFail($id);
        $this->selected = $id;
        $this->resetValidation();
    }

    public function mark(int $sessionId): void
    {
        Gate::authorize('info.attendance.mark');
        $this->resetValidation();
        $this->validate(['selected' => 'required|integer']);
        try {
            $result = app(InfoAttendance::class)->manual(auth()->user(), $this->selected, $sessionId);
        } catch (HttpException $error) {
            if ($error->getStatusCode() !== 409) {
                throw $error;
            }
            $this->addError('check_in', $error->getMessage());

            return;
        }
        $this->message = $result['already_present'] ? 'Already present — no duplicate check-in.' : 'Present — check-in saved.';
        $this->search = '';
        $this->selected = null;
        $this->resetPage();
        $this->dispatch('check-in-saved');
    }

    public function render(): View
    {
        Gate::authorize('info.access');
        $session = app(SessionSchedule::class)->activeAt(now());
        $search = mb_substr(trim($this->search), 0, 100);
        $attendees = InfoAttendee::query()->search($search)->with(['attendanceRecords' => fn ($q) => $q->whereNull('voided_at')->where('conference_session_id', $session?->id ?? 0)])
            ->orderBy('last_name')->orderBy('first_name')->paginate(12);

        return view('livewire.check-in-desk', compact('session', 'attendees'));
    }
}
