<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestRequest;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpReports;
use App\Services\GuestCapture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GuestRecord::class);
        $pagination = $request->validate(['per_page' => 'nullable|integer|in:25,50,100,200']);
        $perPage = (int) ($pagination['per_page'] ?? 25);
        $reports = app(FollowUpReports::class);
        $filters = $reports->filters($request->all());
        $year = (int) ($filters['year'] ?? 2026);
        $query = $reports->base($request->user(), $filters)->with(['session.event', 'activeAssignment.officer']);

        return view('guest.index', ['guests' => $query->orderByDesc('id')->paginate($perPage)->withQueryString(),
            'events' => ConferenceEvent::orderByDesc('year')->get(), 'year' => $year,
            'officers' => $request->user()->can('guest.assign') ? app(FollowUpAssignments::class)->officers()->get() : collect(),
            'sessions' => ConferenceSession::whereHas('event', fn ($q) => $q->where('year', $year))->orderBy('date_local')->orderBy('id')->get()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', GuestRecord::class);

        return $this->form($request, null);
    }

    private function form(Request $request, ?GuestRecord $guest): View
    {
        $input = $request->old() ?: ['full_name' => $guest?->full_name ?? '', 'phone_raw' => $guest?->phone_raw ?? ''];
        $candidates = app(GuestCapture::class)->candidates($request->user(), $input);
        if ($guest) {
            $candidates->whereKeyNot($guest->id);
        }

        return view('guest.form', ['guest' => $guest, 'submissionId' => Str::uuid(),
            'selectedSession' => $guest?->conference_session_id ?? $request->session()->get('guest_entry_session'),
            'sessions' => ConferenceSession::with('event')->orderByDesc('date_local')->orderBy('id')->get(),
            'recentEntries' => $guest ? collect() : GuestRecord::visibleTo($request->user())
                ->where('created_by_user_id', $request->user()->id)->whereNull('archived_at')->whereNull('import_row_id')
                ->with('session')->orderByDesc('id')->limit(3)->get(),
            'candidates' => $candidates->limit(10)->get()]);
    }

    public function store(GuestRequest $request, GuestCapture $capture): RedirectResponse
    {
        $guest = $capture->create($request->user(), $request->validated(), $request->validated('submission_id'), $request->boolean('keep_separate'));
        $request->session()->put('guest_entry_session', $guest->conference_session_id);

        return redirect()->route('guests.create')->with('status', 'Guest saved: '.$guest->full_name.' · '.$guest->phone_raw.'. Ready for the next guest; date selection retained.');
    }

    public function show(GuestRecord $guest): View
    {
        Gate::authorize('view', $guest);

        $reports = app(FollowUpReports::class);
        $actor = auth()->user();
        $canContact = $actor->can('contact', $guest);
        $activities = $canContact ? $reports->guestActivities($actor, $guest)->paginate(20) : null;
        $history = $canContact ? $guest->statusHistory()->whereIn('activity_id', $reports->guestActivities($actor, $guest)->reorder()->select('follow_up_activities.id'))->orderByDesc('id')->paginate(20, ['*'], 'history_page') : null;

        return view('guest.show', ['guest' => $guest->load(['session.event', 'activeAssignment.officer']),
            'activities' => $activities, 'history' => $history,
            'assignments' => $actor->can('guest.assign') ? $guest->assignments()->with('officer')->orderByDesc('id')->paginate(20, ['*'], 'assignment_page') : null,
            'submissionId' => Str::uuid()]);
    }

    public function edit(Request $request, GuestRecord $guest): View
    {
        Gate::authorize('update', $guest);

        return $this->form($request, $guest);
    }

    public function update(GuestRequest $request, GuestRecord $guest, GuestCapture $capture): RedirectResponse
    {
        $capture->update($request->user(), $guest, $request->validated(), (int) $request->validated('version'), $request->boolean('keep_separate'));

        return redirect()->route('guests.show', $guest)->with('status', 'Guest details updated.');
    }
}
