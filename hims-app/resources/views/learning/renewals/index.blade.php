@extends('layouts.hims')
@section('title','Renewal Risk')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Renewals')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Renewal Risk</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Everyone whose current cycle will not close with the hours it needs — judged on the pace
            they are keeping, not on whether the deadline has already passed. Only <strong>verified</strong>
            CPD counts toward a cycle.
        </p>
    </div>
    @can('manage-compliance')
    <a href="{{ route('learning.renewals.rules') }}" class="btn-hims btn-hims-outline"><i class="bi bi-book"></i> Renewal Rules</a>
    @endcan
</div>

<div class="hims-card mb-4">
    <div class="card-body">
        {{-- House filter shape: no Apply button, the select submits its own form,
             and @selected reads the value back off the request. --}}
        <form method="GET" action="{{ route('learning.renewals.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="hims-label" for="rn_department">Department</label>
                <select name="department" id="rn_department" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">All departments visible to me</option>
                    @foreach($departments as $department)
                    <option value="{{ $department->department_id }}" @selected($departmentId === $department->department_id)>
                        {{ $department->department_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @if($departmentId)
            <div class="col-md-4">
                <a href="{{ route('learning.renewals.index') }}" class="btn-hims btn-hims-outline">Clear filter</a>
            </div>
            @endif
        </form>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-hourglass-split"></i> At Risk &amp; Short</h5>
        <span style="font-size:12px;color:#6b7280">{{ $cycles->count() }} cycle(s)</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Department</th><th>Requirement</th>
                    <th>Progress</th><th>Cycle Ends</th><th>Standing</th><th>Escalates To</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cycles as $cycle)
                <tr>
                    <td data-label="Employee">
                        <div style="font-weight:600">{{ $cycle->first_name }} {{ $cycle->last_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ $cycle->employee_code }}</div>
                    </td>
                    <td data-label="Department">{{ $cycle->department_name ?? '—' }}</td>
                    <td data-label="Requirement">
                        <div>{{ $cycle->label }}</div>
                        <div style="font-size:11px;color:#9ca3af">
                            {{ $cycle->subject_key }}
                            @if($cycle->credential_number) · {{ $cycle->credential_number }} @endif
                        </div>
                    </td>
                    <td data-label="Progress" style="min-width:150px">
                        <div class="d-flex justify-content-between" style="font-size:11.5px;margin-bottom:4px">
                            <span>{{ $cycle->hours_attained }} / {{ rtrim(rtrim(number_format($cycle->hours_required_snapshot, 1), '0'), '.') }} hrs</span>
                            <strong>{{ $cycle->pct_complete }}%</strong>
                        </div>
                        <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $cycle->pct_complete }}%"></div></div>
                    </td>
                    <td data-label="Cycle Ends">
                        {{ \Carbon\Carbon::parse($cycle->cycle_end)->format('M d, Y') }}
                        <div style="font-size:11px;color:#9ca3af">
                            {{ $cycle->days_left >= 0 ? $cycle->days_left.' day(s) left' : abs($cycle->days_left).' day(s) ago' }}
                            @if($cycle->grace_days) · +{{ $cycle->grace_days }} grace @endif
                        </div>
                        @if($cycle->credential_status)
                        <span class="hims-badge {{ \App\Support\CredentialStatus::badgeClass($cycle->credential_status) }}" style="margin-top:4px">
                            {{ \App\Support\CredentialStatus::label($cycle->credential_status) }}
                        </span>
                        @endif
                    </td>
                    <td data-label="Standing">
                        @if($cycle->risk === 'shortfall')
                            <span class="hims-badge red">Closed short by {{ $cycle->hours_remaining }} hrs</span>
                        @else
                            <span class="hims-badge yellow">{{ $cycle->hours_remaining }} hrs still needed</span>
                        @endif
                    </td>
                    <td data-label="Escalates To">
                        @if($cycle->email)
                            <span style="font-size:12px;color:#6b7280">{{ $cycle->email }}</span>
                        @elseif($cycle->supervisor_id)
                            <span class="hims-badge blue">Supervisor only</span>
                        @else
                            <span class="hims-badge gray">Nobody reachable</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:32px">
                    Nobody in view is tracking short. If that reads wrong, check that
                    @can('manage-compliance')
                    <a href="{{ route('learning.renewals.rules') }}" class="text-primary-hims">renewal rules</a>
                    @else
                    renewal rules
                    @endcan
                    exist and that cycles have been opened.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-alert info mt-3">
    <i class="bi bi-info-circle-fill"></i>
    <strong>At risk</strong> means finishing on time now needs a run-rate half again as fast as the pace
    kept so far, or the window closes within {{ \App\Support\CredentialStatus::WINDOW_DAYS }} days with
    hours still owed. <strong>Closed short</strong> means the window has already passed.
</div>
@endsection
