<x-layout title="Attendance check-in">
<div class="page-heading"><div><span class="eyebrow">INFO COM · ATTENDANCE</span><h1>Attendance check-in</h1><p>Find the right attendee, confirm their identity, and mark the active session present.</p></div><a href="{{ route('info.attendees.create') }}" class="button primary">Register attendee <x-icon name="arrow" /></a></div>
<livewire:check-in-desk />
</x-layout>

