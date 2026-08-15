@foreach($assignments as $assignment)
@push('modals')
<div class="hims-modal-backdrop assignment-roster-modal" id="assignmentRoster{{ $assignment->assignment_id }}"
     role="dialog" aria-modal="true" aria-labelledby="assignmentRosterTitle{{ $assignment->assignment_id }}">
    <div class="hims-modal" style="max-width:980px">
        <div class="hims-modal-header">
            <div>
                <h5 id="assignmentRosterTitle{{ $assignment->assignment_id }}" style="margin:0">{{ $assignment->subject_name }}</h5>
                <div style="font-size:12px;color:#6b7280;margin-top:3px">
                    Required of {{ $assignment->target_name }}
                    @if($assignment->required_by) · due {{ \Carbon\Carbon::parse($assignment->required_by)->format('M d, Y') }}@endif
                </div>
            </div>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <div class="hims-modal-body">
            <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                <div class="d-flex gap-2">
                    <span class="hims-badge green">{{ $assignment->compliance['complete'] }} complete</span>
                    <span class="hims-badge yellow">{{ $assignment->compliance['outstanding'] }} outstanding</span>
                </div>
                <select class="hims-input hims-select assignment-roster-filter" style="max-width:210px"
                        aria-label="Filter assignment roster">
                    <option value="all">Everyone assigned</option>
                    <option value="outstanding">Still outstanding</option>
                    <option value="complete">Completed</option>
                </select>
            </div>
            <div style="overflow-x:auto">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Contact</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @forelse($assignment->visible_roster as $row)
                        <tr data-roster-state="{{ $row->is_complete ? 'complete' : 'outstanding' }}">
                            <td><strong>{{ $row->first_name }} {{ $row->last_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $row->employee_code }}</div></td>
                            <td>{{ $row->department_name ?? '—' }}</td>
                            <td>
                                @if($row->is_complete)
                                    <span class="hims-badge green">Complete</span>
                                @elseif($assignment->required_by && now()->toDateString() > $assignment->required_by)
                                    <span class="hims-badge red">Overdue</span>
                                @else
                                    <span class="hims-badge yellow">Outstanding</span>
                                @endif
                            </td>
                            <td>{{ $row->email ?: 'No email on file' }}</td>
                            <td class="text-end" style="white-space:nowrap">
                                @if($assignment->subject_type === 'session')
                                    <a href="{{ route('training.sessions.show', $assignment->subject_id) }}" class="btn-hims btn-hims-ghost btn-sm">Attendance</a>
                                @elseif(! $row->is_complete)
                                    <form method="POST" action="{{ route('learning.enrollments.complete', $row->record_id) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn-hims btn-hims-primary btn-sm">Mark complete</button>
                                    </form>
                                @elseif(auth()->user()->can('manage-learning'))
                                    <form method="POST" action="{{ route('learning.enrollments.reopen', $row->record_id) }}" style="display:inline" onsubmit="return confirm('Withdraw this completion and remove its CPD credit?')">
                                        @csrf
                                        <button type="submit" class="btn-hims btn-hims-ghost btn-sm">Reopen</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:28px">Nobody on this assignment is visible to you.</td></tr>
                    @endforelse
                        <tr data-roster-empty style="display:none"><td colspan="5" class="text-center" style="color:#9ca3af;padding:28px">No people match this filter.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="hims-modal-footer">
            <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Close</button>
        </div>
    </div>
</div>
@endpush
@endforeach

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-roster-filter][data-modal-open]');
    if (!trigger) return;
    var modal = document.getElementById(trigger.getAttribute('data-modal-open'));
    var select = modal && modal.querySelector('.assignment-roster-filter');
    if (select) {
        select.value = trigger.getAttribute('data-roster-filter') || 'all';
        select.dispatchEvent(new Event('change'));
    }
});

document.querySelectorAll('.assignment-roster-filter').forEach(function (select) {
    select.addEventListener('change', function () {
        var modal = select.closest('.assignment-roster-modal');
        var shown = 0;
        modal.querySelectorAll('[data-roster-state]').forEach(function (row) {
            var visible = select.value === 'all' || row.getAttribute('data-roster-state') === select.value;
            row.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
        var empty = modal.querySelector('[data-roster-empty]');
        if (empty) empty.style.display = shown ? 'none' : '';
    });
});
</script>
@endpush
