@extends('layouts.hims')
@section('title','Assignment Roster')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Required Training / Roster')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $assignment->subject_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ ucfirst($assignment->subject_type) }} required of <strong>{{ $assignment->target_name }}</strong>
            @if($assigner) · assigned by {{ $assigner->first_name }} {{ $assigner->last_name }} @endif
            · {{ \Carbon\Carbon::parse($assignment->created_at)->format('M d, Y') }}
        </p>
    </div>
</div>

@if($assignment->reason)
<div class="hims-alert info mb-4"><i class="bi bi-info-circle-fill"></i> {{ $assignment->reason }}</div>
@endif

{{-- No flash block here: layouts.hims renders session success/error above
     @yield('content') for every page. --}}

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">{{ $compliance['total'] }}</div><div class="stat-label">Assigned</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $compliance['complete'] }}</div><div class="stat-label">Completed</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value">{{ $compliance['outstanding'] }}</div><div class="stat-label">Outstanding</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value">{{ $compliance['rate'] }}%</div><div class="stat-label">Compliance Rate</div></div></div>
</div>

@if($compliance['overdue'] > 0)
<div class="hims-alert error mb-4">
    <i class="bi bi-exclamation-circle-fill"></i>
    {{ $compliance['overdue'] }} person(s) are past the {{ \Carbon\Carbon::parse($assignment->required_by)->format('M d, Y') }} deadline.
</div>
@endif

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-people"></i> Roster</h5>
        <div class="d-flex align-items-center gap-3">
            {{-- House filter shape: GET form, submit on change, @selected reading
                 the value back off the request so the control still shows the
                 chosen option after the reload. --}}
            <form method="GET" action="{{ route('learning.assignments.show', $assignment->assignment_id) }}">
                <select name="status" class="hims-input hims-select" style="min-width:190px" onchange="this.form.submit()"
                        aria-label="Filter roster by completion">
                    <option value="all" @selected($status === 'all')>Everyone assigned</option>
                    <option value="outstanding" @selected($status === 'outstanding')>Still outstanding</option>
                    <option value="complete" @selected($status === 'complete')>Completed</option>
                </select>
            </form>
            <span style="font-size:12px;color:#6b7280">
                @if($assignment->required_by)
                    Due {{ \Carbon\Carbon::parse($assignment->required_by)->format('M d, Y') }}
                @else
                    No deadline set
                @endif
            </span>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Completed</th><th>Reachable</th><th class="text-end"></th></tr></thead>
            <tbody>
                @forelse($roster as $row)
                <tr>
                    <td>
                        <div style="font-weight:600">{{ $row->first_name }} {{ $row->last_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ $row->employee_code }}</div>
                    </td>
                    <td>{{ $row->department_name ?? '—' }}</td>
                    <td>
                        @if($row->is_complete)
                            <span class="hims-badge green">✓ {{ ucfirst(str_replace('_',' ',$row->status)) }}</span>
                        @elseif($assignment->required_by && now()->toDateString() > $assignment->required_by)
                            <span class="hims-badge red">Overdue</span>
                        @else
                            <span class="hims-badge yellow">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span>
                        @endif
                    </td>
                    <td>{{ $row->completed_at ? \Carbon\Carbon::parse($row->completed_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @if($row->email)
                            <span style="font-size:12px;color:#6b7280">{{ $row->email }}</span>
                        @else
                            <span class="hims-badge gray">No address on file</span>
                        @endif
                    </td>
                    {{-- Each subject type keeps its own completion mechanism:
                         courses are marked here, sessions are checked in on the
                         attendance sheet. Duplicating check-in would give a
                         session two places to record the same fact. --}}
                    <td class="text-end" style="white-space:nowrap">
                        @if($assignment->subject_type === 'session')
                            <a href="{{ route('training.sessions.show', $assignment->subject_id) }}"
                               class="btn-hims btn-hims-ghost btn-sm">Attendance</a>
                        @elseif(! $row->is_complete)
                            <form method="POST" action="{{ route('learning.enrollments.complete', $row->record_id) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn-hims btn-hims-primary btn-sm">
                                    <i class="bi bi-check2"></i> Mark complete
                                </button>
                            </form>
                        @elseif(auth()->user()->can('manage-learning'))
                            <form method="POST" action="{{ route('learning.enrollments.reopen', $row->record_id) }}" style="display:inline"
                                  onsubmit="return confirm('Withdraw this completion and remove its CPD credit?')">
                                @csrf
                                <button type="submit" class="btn-hims btn-hims-ghost btn-sm">Reopen</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    @if($status === 'outstanding')
                        Nobody visible to you is still outstanding on this assignment.
                    @elseif($status === 'complete')
                        Nobody visible to you has completed this assignment yet.
                    @else
                        Nobody on this assignment is visible to you.
                    @endif
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($status === 'all' && $assignment->expanded_count > $roster->count())
<p style="color:#9ca3af;font-size:12px;margin-top:12px">
    Showing {{ $roster->count() }} of {{ $assignment->expanded_count }} assigned — the rest report to a
    different supervisor. The tiles above count everybody; this list counts only the people you may see.
</p>
@endif
@endsection
