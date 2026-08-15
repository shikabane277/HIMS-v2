@extends('layouts.hims')
@section('title','Accreditation Report')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Accreditation Report')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Accreditation Report</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            One row per active employee: competency proficiency, credential standing and training
            completion together. Filter to a department and role to answer "show me every ICU nurse's
            current status" without compiling it by hand.
        </p>
    </div>
    @can('manage-compliance')
    <a href="{{ route('learning.accounts') }}" class="btn-hims btn-hims-outline"><i class="bi bi-person-badge"></i> Account Coverage</a>
    @endcan
</div>

<div class="hims-card mb-4">
    <div class="card-body">
        {{-- House filter shape: each select submits its own form, so there is no
             Apply button to hunt for, and @selected reads the value back off the
             request so the controls still show the chosen options after reload. --}}
        <form method="GET" action="{{ route('learning.accreditation') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="hims-label" for="ac_department">Department</label>
                <select name="department" id="ac_department" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">All visible to me</option>
                    @foreach($departments as $department)
                    <option value="{{ $department->department_id }}" @selected($filters['department'] === $department->department_id)>
                        {{ $department->department_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="hims-label" for="ac_role">Role</label>
                <select name="role" id="ac_role" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">All roles</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->role_id }}" @selected($filters['role'] === $role->role_id)>{{ $role->role_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="hims-label" for="ac_standard">JCI Standard</label>
                <select name="standard" id="ac_standard" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">Every competency</option>
                    @foreach($standards as $code)
                    <option value="{{ $code }}" @selected($filters['standard'] === $code)>{{ $code }}</option>
                    @endforeach
                </select>
                <small style="color:#9ca3af;font-size:11.5px">Narrows the competency columns only.</small>
            </div>
            @if(array_filter($filters))
            <div class="col-md-3">
                <a href="{{ route('learning.accreditation') }}" class="btn-hims btn-hims-outline">Clear filters</a>
            </div>
            @endif
        </form>
    </div>
</div>

@php
    $compliant = $employees->where('compliant', true)->count();
    $rate = $employees->count() > 0 ? round($compliant / $employees->count() * 100, 1) : 0;
@endphp

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">{{ $employees->count() }}</div><div class="stat-label">In Scope</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $compliant }}</div><div class="stat-label">Fully Compliant</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value">{{ $rate }}%</div><div class="stat-label">Compliance Rate</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🚩</div><div class="stat-value">{{ $employees->count() - $compliant }}</div><div class="stat-label">With Findings</div></div></div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-clipboard-data"></i> Staff Standing</h5>
        <span style="font-size:12px;color:#6b7280">Active employment only</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Department</th><th>Role / Position</th>
                    <th>Competency</th><th>Credentials</th><th>Training</th><th>CPD</th><th>Standing</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td>
                        <div style="font-weight:600">{{ $employee->last_name }}, {{ $employee->first_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ $employee->employee_code }}</div>
                    </td>
                    <td>{{ $employee->department_name ?? '—' }}</td>
                    <td>
                        {{ $employee->role_name ?? '—' }}
                        @if($employee->position_title)
                        <div style="font-size:11px;color:#9ca3af">{{ $employee->position_title }}</div>
                        @endif
                    </td>
                    <td>
                        @if($employee->competency)
                            <strong>{{ $employee->competency->at_target }}</strong> / {{ $employee->competency->assessed }} at target
                            <div style="font-size:11px;color:#9ca3af">avg {{ $employee->competency->avg_proficiency }} proficiency</div>
                        @else
                            <span class="hims-badge gray">Not assessed</span>
                        @endif
                    </td>
                    <td>
                        @if($employee->credentials)
                            {{ $employee->credentials->total }} on file
                            <div style="font-size:11px">
                                @if($employee->credentials->expired > 0)
                                    <span class="hims-badge red">{{ $employee->credentials->expired }} expired</span>
                                @endif
                                @if($employee->credentials->expiring > 0)
                                    <span class="hims-badge yellow">{{ $employee->credentials->expiring }} expiring</span>
                                @endif
                                @if($employee->credentials->expired == 0 && $employee->credentials->expiring == 0)
                                    <span class="hims-badge green">Current</span>
                                @endif
                            </div>
                        @else
                            <span class="hims-badge gray">None recorded</span>
                        @endif
                    </td>
                    <td>
                        @if($employee->training)
                            <strong>{{ $employee->training->completed }}</strong> / {{ $employee->training->enrolled }} complete
                            @if($employee->training->outstanding > 0)
                            <div style="font-size:11px"><span class="hims-badge yellow">{{ $employee->training->outstanding }} required outstanding</span></div>
                            @endif
                        @else
                            <span class="hims-badge gray">No enrollments</span>
                        @endif
                    </td>
                    <td>{{ $employee->training ? rtrim(rtrim(number_format($employee->training->cpd_hours, 1), '0'), '.') : '0' }} hrs</td>
                    <td>
                        @if($employee->compliant)
                            <span class="hims-badge green">✓ Clear</span>
                        @else
                            <span class="hims-badge red">Finding</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center" style="color:#9ca3af;padding:32px">
                    No active employee matches those filters.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-alert info mt-3">
    <i class="bi bi-info-circle-fill"></i>
    <strong>Standing</strong> is a finding when a credential has lapsed or a required course is
    unfinished. Every course enrolment is now required of somebody, so the only enrolments excluded
    from the training count are legacy rows created before self-enrolment was removed — those carry no
    assignment, so nobody asked for them by a date. Competency counts use each employee's most recent
    assessment per competency, so a reassessment replaces the earlier score rather than averaging with it.
</div>
@endsection
