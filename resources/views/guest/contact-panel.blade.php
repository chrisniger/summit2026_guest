@can('contact', $guest)
    @unless($guest->archived_at)
        <form method="POST" action="{{ route('follow-up.activities.store', $guest) }}" class="panel edit-panel form-stack" x-data="{ outcome: @js(old('outcome', 'NOTE_ONLY')) }">
            @csrf<h2>Record follow-up</h2><p class="muted-text">Log the actual attempt. A sent message or an unanswered call does not mean two-way contact.</p>
            <input type="hidden" name="submission_id" value="{{ old('submission_id', $submissionId) }}">
            <input type="hidden" name="version" value="{{ $guest->version }}">
            <input type="hidden" name="assignment_id" value="{{ $guest->activeAssignment?->id }}">
            <div class="form-grid">
                <div><label for="contact_method">Method *</label><select id="contact_method" name="method" required>@foreach(['call' => 'Call', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'visit' => 'Visit', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('method', 'call') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="contact_outcome">Outcome *</label><select id="contact_outcome" name="outcome" x-model="outcome" required><option value="NOTE_ONLY">Note only — keep status</option><option value="CONTACTED">Contacted — two-way contact</option><option value="UNREACHABLE">Unreachable — failed attempt</option><option value="WRONG_NUMBER">Wrong number</option></select></div>
                <div><label for="attempted_at">Actual attempt time (WAT) *</label><input id="attempted_at" name="attempted_at" type="datetime-local" value="{{ old('attempted_at', now()->setTimezone('Africa/Lagos')->format('Y-m-d\TH:i')) }}" required></div>
                <div><label for="contact_comment">Comment *</label><textarea id="contact_comment" name="comment" maxlength="5000" required>{{ old('comment') }}</textarea></div>
            </div>
            <label class="checkbox-row" x-show="outcome === 'CONTACTED'" x-cloak><input type="checkbox" name="two_way_confirmed" value="1" @checked(old('two_way_confirmed'))>Actual two-way contact took place.</label>
            <label class="checkbox-row" x-show="outcome === 'UNREACHABLE'" x-cloak><input type="checkbox" name="failed_attempt_confirmed" value="1" @checked(old('failed_attempt_confirmed'))>An actual attempt failed to reach the guest.</label>
            <label class="checkbox-row" x-show="outcome === 'WRONG_NUMBER'" x-cloak><input type="checkbox" name="wrong_number_confirmed" value="1" @checked(old('wrong_number_confirmed'))>I verified that this is the wrong number.</label>
            <p class="muted-text">Earlier dated attempts stay in history. A later dated outcome determines the current status. Notes leave status unchanged.</p>
            <button class="button primary">Save follow-up</button>
        </form>
    @endunless
@endcan
@if($activities)
    <section class="panel edit-panel"><h2>Contact activity</h2>
        @if(! app(\App\Services\FollowUpReports::class)->isManager(auth()->user()))<p class="muted-text">Your activity under the current assignment. Other officers' private activity is restricted.</p>@endif
        @forelse($activities as $activity)
            <article class="guest-source"><strong>{{ $activity->outcome }} · {{ ucfirst($activity->method) }}</strong><p class="muted-text">{{ $activity->attempted_at_utc->setTimezone('Africa/Lagos')->format('d M Y H:i') }} WAT · {{ $activity->actor->name }}</p><p class="activity-comment">{{ $activity->comment }}</p><p class="muted-text">Status at entry: {{ $activity->previous_status }} → {{ $activity->new_status }}</p></article>
        @empty<p class="muted-text guest-section">No activity available in your scope.</p>@endforelse
        <div class="pagination">{{ $activities->links() }}</div>
    </section>
@endif
@if($history)
    <section class="panel edit-panel"><h2>Status history</h2><ul class="breakdown-list">@forelse($history as $entry)<li><span>{{ $entry->from_status }} → {{ $entry->to_status }}<br>{{ $entry->reason }}</span><span>{{ $entry->changed_at_utc->setTimezone('Africa/Lagos')->format('d M Y H:i') }} WAT</span></li>@empty<li>No status changes available in your scope.</li>@endforelse</ul><div class="pagination">{{ $history->links() }}</div></section>
@endif
@if($assignments)
    <section class="panel edit-panel"><h2>Assignment history</h2><ul class="breakdown-list">@forelse($assignments as $assignment)<li><span><strong>{{ $assignment->officer->name }}</strong><br>{{ $assignment->reason }}@if($assignment->closure_reason)<br>Closed: {{ $assignment->closure_reason }}@endif</span><span>{{ $assignment->assigned_at->setTimezone('Africa/Lagos')->format('d M Y H:i') }} WAT<br>{{ $assignment->unassigned_at ? 'Ended '.$assignment->unassigned_at->setTimezone('Africa/Lagos')->format('d M Y H:i').' WAT' : 'CURRENT' }}</span></li>@empty<li>No assignments yet.</li>@endforelse</ul><div class="pagination">{{ $assignments->links() }}</div></section>
@endif
