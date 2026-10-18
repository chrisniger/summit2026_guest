<x-layout title="Audit trail">
<div class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Audit trail</h1><p>Staff access and configuration history. Sensitive field values are excluded.</p></div></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Time (WAT)</th><th>Domain</th><th>Action</th><th>Actor ID</th><th>Record ID</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->occurred_at->setTimezone(config('summit.timezone'))->format('j M Y H:i:s') }}</td><td>{{ str_replace('_', ' ', $log->domain) }}</td><td>{{ $log->action }}</td><td>{{ $log->actor_user_id ?? 'System' }}</td><td>{{ $log->subject_id }}</td></tr>@empty<tr><td colspan="5"><div class="empty-state"><x-icon name="shield" /><strong>No audit events yet</strong><p>Events appear here as staff sign in and administrators configure the system.</p></div></td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $logs->links() }}</div></section>
</x-layout>

