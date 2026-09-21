@extends('layouts.hims')
@section('title','Required Training')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Required Training')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Required Training &amp; Renewal Standing</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            What the hospital requires of its staff, who is short of it, and how long they have left.
            Everything here works from the employee record — a login account is not needed.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('learning.accreditation') }}" class="btn-hims btn-hims-outline"><i class="bi bi-clipboard-data"></i> Accreditation Report</a>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="assignmentCreateModal">
            <i class="bi bi-person-plus"></i> Assign Training
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-pin-angle"></i></div><div class="stat-value">{{ $stats['open_assignments'] }}</div><div class="stat-label">Assignments Still Outstanding</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value">{{ $stats['at_risk'] }}</div><div class="stat-label">Cycles At Risk</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-x-circle"></i></div><div class="stat-value">{{ $stats['shortfall'] }}</div><div class="stat-label">Closed Short</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-journal-text"></i></div><div class="stat-value">{{ $stats['rules'] }}</div><div class="stat-label">Active Renewal Rules</div></div></div>
</div>

<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-list-check"></i> Mandatory Training Assignments</h5>
        <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-modal-open="assignmentCreateModal">New</button>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Course / Session</th><th>Assigned To</th><th>Required By</th><th>Completion</th><th>Outstanding</th><th></th></tr></thead>
            <tbody>
                @forelse($assignments as $assignment)
                <tr>
                    <td data-label="Course / Session">
                        <div style="font-weight:600">{{ $assignment->subject_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ ucfirst($assignment->subject_type) }} · assigned {{ \Carbon\Carbon::parse($assignment->created_at)->format('M d, Y') }}</div>
                    </td>
                    <td data-label="Assigned To">{{ $assignment->target_name }}</td>
                    <td data-label="Required By">
                        @if($assignment->required_by)
                            {{ \Carbon\Carbon::parse($assignment->required_by)->format('M d, Y') }}
                        @else
                            <span style="color:#9ca3af">No deadline</span>
                        @endif
                    </td>
                    <td data-label="Completion" style="min-width:150px">
                        <div class="d-flex justify-content-between" style="font-size:11.5px;margin-bottom:4px">
                            <span>{{ $assignment->compliance['complete'] }} / {{ $assignment->compliance['total'] }}</span>
                            <strong>{{ $assignment->compliance['rate'] }}%</strong>
                        </div>
                        <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $assignment->compliance['rate'] }}%"></div></div>
                    </td>
                    {{-- The badge says "outstanding", matching its own column header
                         and the roster tile, and links straight to the filtered
                         roster — so "who is short" is answered by a filter rather
                         than by hunting through a full list. --}}
                    <td data-label="Outstanding">
                        @if($assignment->compliance['overdue'] > 0)
                            <button type="button" class="hims-badge red" style="border:0;cursor:pointer"
                                    data-modal-open="assignmentRoster{{ $assignment->assignment_id }}"
                                    data-roster-filter="outstanding">{{ $assignment->compliance['overdue'] }} overdue</button>
                        @elseif($assignment->compliance['outstanding'] > 0)
                            <button type="button" class="hims-badge yellow" style="border:0;cursor:pointer"
                                    data-modal-open="assignmentRoster{{ $assignment->assignment_id }}"
                                    data-roster-filter="outstanding">{{ $assignment->compliance['outstanding'] }} outstanding</button>
                        @else
                            <span class="hims-badge green">✓ Complete</span>
                        @endif
                    </td>
                    <td data-label="Actions"><button type="button" class="btn-hims btn-hims-ghost btn-sm"
                                data-modal-open="assignmentRoster{{ $assignment->assignment_id }}"
                                data-roster-filter="all">Roster</button></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    Nothing has been required of anyone yet.
                    <button type="button" class="hims-link-button text-primary-hims" data-modal-open="assignmentCreateModal">Assign training</button>.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-hourglass-split"></i> Renewal Risk</h5>
        <div class="d-flex gap-2">
            @can('manage-compliance')
            <a href="{{ route('learning.renewals.rules') }}" class="btn-hims btn-hims-ghost btn-sm">Rules</a>
            @endcan
            <a href="{{ route('learning.renewals.index') }}" class="btn-hims btn-hims-ghost btn-sm">Full list</a>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Requirement</th><th>Hours</th><th>Cycle Ends</th><th>Standing</th></tr></thead>
            <tbody>
                @forelse($atRisk->take(12) as $cycle)
                <tr>
                    <td data-label="Employee">
                        <div style="font-weight:600">{{ $cycle->first_name }} {{ $cycle->last_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ $cycle->employee_code }}</div>
                    </td>
                    <td data-label="Requirement">{{ $cycle->label }}</td>
                    <td data-label="Hours"><strong>{{ $cycle->hours_attained }}</strong> / {{ $cycle->hours_required_snapshot }}</td>
                    <td data-label="Cycle Ends">
                        {{ \Carbon\Carbon::parse($cycle->cycle_end)->format('M d, Y') }}
                        <div style="font-size:11px;color:#9ca3af">
                            {{ $cycle->days_left >= 0 ? $cycle->days_left.' day(s) left' : abs($cycle->days_left).' day(s) ago' }}
                        </div>
                    </td>
                    <td data-label="Standing">
                        @if($cycle->risk === 'shortfall')
                            <span class="hims-badge red">Short by {{ $cycle->hours_remaining }} hrs</span>
                        @else
                            <span class="hims-badge yellow">{{ $cycle->hours_remaining }} hrs still needed</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">
                    Nobody is behind. If that looks wrong, no renewal rules may be set yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('learning.assignments._create-modal')
@include('learning.assignments._roster-modals')
@endsection
