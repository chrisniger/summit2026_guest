<x-layout title="Attendance">
<div class="page-heading is-compact"><div><h1>Attendance</h1><p>Mark the active session present.</p></div><a href="{{ route('info.attendees.create') }}" class="button primary">Register <x-icon name="arrow" /></a></div>
<livewire:check-in-desk />
</x-layout>
