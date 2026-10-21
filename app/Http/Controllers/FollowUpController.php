<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowUpActivityRequest;
use App\Models\GuestRecord;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function preview(Request $request, FollowUpAssignments $assignments): View
    {
        return view('guest.assignment-confirm', $assignments->preview($request->user(), $request->all()));
    }

    public function commit(Request $request, FollowUpAssignments $assignments): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|max:100000', 'confirm' => 'required|accepted']);
        $result = $assignments->commit($request->user(), $data['token']);

        return redirect()->route('guests.index')->with('status', "Assignments saved: {$result['assigned']} assigned, {$result['reassigned']} reassigned, {$result['unassigned']} unassigned, {$result['unchanged']} unchanged.");
    }

    public function activity(FollowUpActivityRequest $request, GuestRecord $guest, FollowUpContact $contact): RedirectResponse
    {
        $activity = $contact->record($request->user(), $guest, $request->validated());
        $outcomeStatus = $activity->outcome === 'WRONG_NUMBER' ? 'WRONG NUMBER' : $activity->outcome;
        $message = $activity->outcome === 'NOTE_ONLY' ? 'Note saved. Contact status is unchanged.'
            : ($activity->new_status === $outcomeStatus ? 'Follow-up outcome saved.' : 'Earlier attempt saved. A later attempt determines the current status.');

        if ($request->validated('return_to') === 'list') {
            return redirect()->route('guests.index', $request->query())->with('status', $message);
        }

        return redirect()->route('guests.show', $guest)->with('status', $message);
    }
}
