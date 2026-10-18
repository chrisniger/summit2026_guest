<x-layout title="Local operations">
    <div class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>Local operations</h1><p>Read-only checks for this internal app. Refresh after resolving a check.</p></div><a class="button secondary" href="{{ route('admin.operations') }}">Refresh checks</a></div>
    <section class="panel edit-panel"><ul class="breakdown-list">@foreach($checks as $check)<li><span><strong>{{ $check['check'] }}</strong><br>{{ $check['message'] }}</span><span class="badge">{{ strtoupper($check['status']) }}</span></li>@endforeach</ul></section>
    <section class="panel edit-panel"><h2>Operator recovery</h2><p class="muted-text guest-section">Stalled imports can be marked paused by the operator CLI, then retried through their original import screens. Completed originals are removed only with an explicitly chosen retention age. Operational backup and recovery instructions are in the local runbook.</p></section>
</x-layout>
