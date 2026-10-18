<x-layout title="Confirm assignment changes">
    <div class="page-heading"><div><p class="eyebrow">FOLLOW-UP OPERATIONS</p><h1>Review assignment changes</h1><p>{{ $guests->count() }} selected encounters · {{ ucfirst($action) }}</p></div><a class="button secondary" href="{{ route('guests.index') }}">Cancel</a></div>
    <section class="panel edit-panel"><h2>{{ $action === 'unassign' ? 'Remove current assignments' : 'New officer: '.$officer->name }}</h2><p class="guest-source">Reason: {{ $reason }}</p><p class="muted-text">All selected changes save together. If any encounter changes before confirmation, review the selection again.</p>
        <ul class="breakdown-list">@foreach($guests as $guest)<li><span><strong>{{ $guest->full_name }}</strong><br>{{ $guest->session->date_local->format('d M Y') }} · {{ $guest->session->label }}<br>Current officer: {{ $guest->activeAssignment?->officer?->name ?? 'UNASSIGNED' }}</span><span>#{{ $guest->id }}</span></li>@endforeach</ul>
        <form method="POST" action="{{ route('follow-up.assignments.commit') }}" class="form-stack guest-section">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I confirm {{ $action }} for these {{ $guests->count() }} encounters.</label>
            <button class="button primary">Confirm assignment changes</button>
        </form>
    </section>
</x-layout>
