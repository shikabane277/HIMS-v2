@extends('layouts.hims')
@section('title','Manager Setup')
@section('page-title','Manager Setup')
@section('breadcrumb','HIMS / Employees / Manager Setup')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Manager Setup Report</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            People Manager controls who may appear in Reports To. Account role controls what they may do inside HIMS.
        </p>
    </div>
    <div class="d-flex gap-2">
        @can('manage-users')
            <a href="{{ route('users.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-shield-lock"></i> User Accounts</a>
        @endcan
        <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Employees</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md col-6"><div class="stat-card"><div class="stat-value">{{ $peopleManagersWithoutAccounts->count() }}</div><div class="stat-label">No Account</div></div></div>
    <div class="col-md col-6"><div class="stat-card"><div class="stat-value">{{ $peopleManagersWithStaffAccess->count() }}</div><div class="stat-label">Staff-only Access</div></div></div>
    <div class="col-md col-6"><div class="stat-card"><div class="stat-value">{{ $supervisorAccountsNotManagers->count() }}</div><div class="stat-label">Supervisor Not Manager</div></div></div>
    <div class="col-md col-6"><div class="stat-card"><div class="stat-value">{{ $inactiveManagersWithActiveReports->count() }}</div><div class="stat-label">Inactive With Reports</div></div></div>
    <div class="col-md col-6"><div class="stat-card"><div class="stat-value">{{ $employeesWithoutManagers->count() }}</div><div class="stat-label">No Manager</div></div></div>
</div>

@php
    $reportSections = [
        ['title' => 'People Managers without HIMS accounts', 'rows' => $peopleManagersWithoutAccounts, 'message' => 'All People Managers have linked HIMS accounts.'],
        ['title' => 'People Managers with Staff-only access', 'rows' => $peopleManagersWithStaffAccess, 'message' => 'No People Manager is limited to Staff access.'],
        ['title' => 'Supervisor accounts not marked as People Managers', 'rows' => $supervisorAccountsNotManagers, 'message' => 'Every Supervisor account is aligned with People Manager status.'],
    ];
@endphp

@foreach($reportSections as $section)
<div class="hims-card mb-4">
    <div class="card-header"><h5><i class="bi bi-exclamation-triangle"></i> {{ $section['title'] }}</h5></div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Position</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($section['rows'] as $employee)
                    <tr>
                        <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $employee->employee_code }}</div></td>
                        <td>{{ $employee->position_title ?: 'No position title' }}</td>
                        <td>{{ $employee->department_name }}</td>
                        <td><span class="hims-badge {{ $employee->employment_status === 'active' ? 'green' : 'gray' }}">{{ ucfirst(str_replace('_', ' ', $employee->employment_status)) }}</span></td>
                        <td><a href="{{ route('employees.edit', $employee->employee_id) }}" class="btn-hims btn-hims-outline btn-sm">Edit Employee</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center" style="padding:28px;color:#6b7280">{{ $section['message'] }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach

<div class="hims-card mb-4">
    <div class="card-header"><h5><i class="bi bi-person-dash"></i> Inactive managers with active direct reports</h5></div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Manager</th><th>Status</th><th>Active Direct Reports</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($inactiveManagersWithActiveReports as $manager)
                    <tr>
                        <td><strong>{{ $manager->first_name }} {{ $manager->last_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $manager->department_name }}</div></td>
                        <td><span class="hims-badge gray">{{ ucfirst(str_replace('_', ' ', $manager->employment_status)) }}</span></td>
                        <td>{{ $manager->active_direct_reports->map(fn($report) => $report->first_name.' '.$report->last_name)->implode(', ') }}</td>
                        <td><a href="{{ route('employees.edit', $manager->employee_id) }}" class="btn-hims btn-hims-outline btn-sm">Edit Manager</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center" style="padding:28px;color:#6b7280">No inactive People Manager has active direct reports.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-card">
    <div class="card-header"><h5><i class="bi bi-diagram-2"></i> Active employees with no manager</h5></div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Position</th><th>Department</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($employeesWithoutManagers as $employee)
                    <tr>
                        <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $employee->employee_code }}</div></td>
                        <td>{{ $employee->position_title ?: 'No position title' }}</td>
                        <td>{{ $employee->department_name }}</td>
                        <td><a href="{{ route('employees.edit', $employee->employee_id) }}" class="btn-hims btn-hims-outline btn-sm">Assign Manager</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center" style="padding:28px;color:#6b7280">Every active employee has a manager.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
