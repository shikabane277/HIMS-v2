@extends('layouts.hims')
@section('title','Employees')
@section('page-title','Employees')
@section('breadcrumb','HIMS / Employees')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Employee Directory</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            @can('manage-employees')
                Manage all hospital staff records, roles, and department assignments.
            @else
                Staff records for your department.
            @endcan
        </p>
    </div>
    @can('manage-employees')
    <a href="{{ route('employees.create') }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-person-plus-fill"></i> Add Employee
    </a>
    @endcan
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $employees->total() }}</div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value">{{ $employees->where('employment_status','active')->count() }}</div>
            <div class="stat-label">Active Staff</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-value">{{ $departments->count() }}</div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $employees->pluck('role_name')->unique()->count() }}</div>
            <div class="stat-label">Roles on This Page</div>
        </div>
    </div>
</div>

<div class="hims-card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h5><i class="bi bi-people-fill"></i> All Employees</h5>
        {{-- Server-side filtering, so it searches every record rather than just this page. --}}
        <form method="GET" action="{{ route('employees.index') }}" class="d-flex gap-2" style="flex-wrap:wrap">
            <input type="text" name="q" class="hims-input" placeholder="Search name, code, email…"
                   style="width:220px;padding:7px 12px;font-size:13px"
                   value="{{ $filters['q'] ?? '' }}">
            <select name="department" class="hims-input hims-select" style="width:170px;padding:7px 12px;font-size:13px">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->department_id }}" @selected(($filters['department'] ?? null) === $dept->department_id)>
                        {{ $dept->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-search"></i> Filter</button>
            @if(($filters['q'] ?? '') !== '' || ($filters['department'] ?? null))
                <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost btn-sm">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table" id="empTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Code</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--hims-primary-dark);flex-shrink:0">
                                {{ strtoupper(substr($emp->first_name,0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $emp->position_title ?? '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12.5px;color:#6b7280;font-family:monospace">{{ $emp->employee_code }}</td>
                    <td>
                        <span class="hims-badge green">{{ $emp->department_name }}</span>
                    </td>
                    <td style="font-size:13px">{{ $emp->role_name }}</td>
                    <td style="font-size:12.5px;color:#6b7280">
                        {{ \Carbon\Carbon::parse($emp->hire_date)->format('M d, Y') }}
                    </td>
                    <td>
                        <span class="hims-badge {{ $emp->employment_status === 'active' ? 'green' : 'gray' }}">
                            <span class="status-dot {{ $emp->employment_status === 'active' ? 'active' : 'inactive' }}"></span>
                            {{ ucfirst($emp->employment_status) }}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center">
                            <a href="{{ route('employees.show', $emp->employee_id) }}" class="btn-hims btn-hims-ghost btn-sm">
                                <i class="bi bi-eye"></i> View
                            </a>
                            @can('manage-employees')
                            <a href="{{ route('employees.edit', $emp->employee_id) }}" class="btn-hims btn-hims-outline btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($emp->employment_status !== 'terminated')
                            <form method="POST" action="{{ route('employees.destroy', $emp->employee_id) }}"
                                  onsubmit="return confirm('Deactivate {{ addslashes($emp->first_name.' '.$emp->last_name) }}? The record is kept but marked terminated.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;cursor:pointer;border-radius:8px;padding:6px 10px;font-size:12px"
                                        title="Deactivate employee">
                                    <i class="bi bi-person-dash"></i>
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#9ca3af;padding:48px">
                        <div style="font-size:40px;margin-bottom:10px">👥</div>
                        @if(($filters['q'] ?? '') !== '' || ($filters['department'] ?? null))
                            No employees match that filter.
                            <a href="{{ route('employees.index') }}" class="text-primary-hims">Clear the filter</a>.
                        @else
                            No employees found.
                            @can('manage-employees')
                                <a href="{{ route('employees.create') }}" class="text-primary-hims">Add the first one</a>.
                            @endcan
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($employees->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">
        {{ $employees->links() }}
    </div>
    @endif
</div>

@endsection
