@extends('layouts.hims')
@section('title','Account Coverage')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Account Coverage')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Account Coverage</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Which employees can sign in and which are tracked on their behalf. Being proxy-tracked is a
            supported state, not a defect — every compliance feature works without a login. This page
            exists so "nobody told them" can be answered with a list.
        </p>
    </div>
    <a href="{{ route('learning.accreditation') }}" class="btn-hims btn-hims-outline"><i class="bi bi-clipboard-data"></i> Accreditation Report</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">{{ $totals->total }}</div><div class="stat-label">Employee Records</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">🔗</div><div class="stat-value">{{ $totals->total - $totals->unlinked }}</div><div class="stat-label">With a Login</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">📭</div><div class="stat-value">{{ $totals->unreachable }}</div><div class="stat-label">No Login, No Email</div></div></div>
</div>

@if($totals->unreachable > 0)
<div class="hims-alert warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    {{ $totals->unreachable }} employee(s) have neither an account nor an email address, so an expiry
    alert can only reach their supervisor. Adding an address to the employee record is enough — a
    login is not required.
</div>
@endif

<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-person-badge"></i> Employees</h5>
        {{-- Three mutually exclusive states, so this stays a segmented control
             rather than a select: all three options are visible at once and the
             active one is the current answer. --}}
        <div class="d-flex gap-2">
            @foreach(['all' => 'All', 'linked' => 'With login', 'unlinked' => 'Proxy-tracked'] as $key => $label)
            <a href="{{ route('learning.accounts', ['filter' => $key]) }}"
               class="btn-hims {{ $filter === $key ? 'btn-hims-primary' : 'btn-hims-ghost' }} btn-sm">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Employee</th><th>Department</th><th>Employment</th><th>Account</th><th>Role</th><th>Reachable At</th></tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td data-label="Employee">
                        <div style="font-weight:600">{{ $employee->last_name }}, {{ $employee->first_name }}</div>
                        <div style="font-size:11px;color:#9ca3af">
                            {{ $employee->employee_code }}
                            @if($employee->position_title) · {{ $employee->position_title }} @endif
                        </div>
                    </td>
                    <td data-label="Department">{{ $employee->department_name ?? '—' }}</td>
                    <td data-label="Employment">
                        <span class="hims-badge {{ $employee->employment_status === 'active' ? 'green' : 'gray' }}">
                            {{ ucfirst(str_replace('_', ' ', $employee->employment_status)) }}
                        </span>
                    </td>
                    <td data-label="Account">
                        @if($employee->user_id)
                            <span class="hims-badge blue">Linked</span>
                            @if(! $employee->email_verified_at)
                            <div style="font-size:11px;color:#9ca3af">Email not verified</div>
                            @endif
                        @else
                            <span class="hims-badge gray">Proxy-tracked</span>
                        @endif
                    </td>
                    <td data-label="Role">{{ $employee->role ? ucfirst(str_replace('_', ' ', $employee->role)) : '—' }}</td>
                    <td data-label="Reachable At">
                        @if($employee->account_email && $employee->account_email !== $employee->email)
                            <div style="font-size:12px">{{ $employee->account_email }}</div>
                            <div style="font-size:11px;color:#9ca3af">employee record: {{ $employee->email ?: 'none' }}</div>
                        @elseif($employee->email)
                            <span style="font-size:12px;color:#6b7280">{{ $employee->email }}</span>
                        @else
                            <span class="hims-badge red">No address</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    No employee matches that filter.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($orphanAccounts->isNotEmpty())
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-person-exclamation"></i> Logins With No Employee Record</h5>
        <span style="font-size:12px;color:#6b7280">{{ $orphanAccounts->count() }} account(s)</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
            <tbody>
                @foreach($orphanAccounts as $account)
                <tr>
                    <td data-label="Name" style="font-weight:600">{{ $account->name }}</td>
                    <td data-label="Email">{{ $account->email }}</td>
                    <td data-label="Role"><span class="hims-badge purple">{{ ucfirst(str_replace('_', ' ', $account->role)) }}</span></td>
                    <td data-label="Actions">
                        {{-- User management is admin-only, a tier tighter than this page.
                             Editing a user is a modal on the user list, and a button on
                             this page cannot open a modal on that one — so this is a
                             deep-link that opens it on arrival. --}}
                        @can('manage-users')
                        <a href="{{ route('users.index', ['edit' => $account->id]) }}" class="btn-hims btn-hims-ghost btn-sm">Link</a>
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<p style="color:#9ca3af;font-size:12px;margin-top:12px">
    These accounts can sign in but have no employee record behind them, so nothing they do is
    attributed to a person in the compliance reports. The seeded administrator is normally one of them.
</p>
@endif
@endsection
