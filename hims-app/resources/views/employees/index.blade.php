@extends('layouts.hims')
@section('title','Employees')
@section('page-title','Employees')
@section('breadcrumb','HIMS / Employees')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Employee Directory</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage all hospital staff records, roles, and department assignments.</p>
    </div>
    <a href="{{ route('employees.create') }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-person-plus-fill"></i> Add Employee
    </a>
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
            <div class="stat-value">{{ $employees->pluck('department_name')->unique()->count() }}</div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $employees->pluck('role_name')->unique()->count() }}</div>
            <div class="stat-label">Distinct Roles</div>
        </div>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-people-fill"></i> All Employees</h5>
        <div class="d-flex gap-2">
            <input type="text" id="empSearch" class="hims-input" placeholder="Search name, code, position…"
                   style="width:220px;padding:7px 12px;font-size:13px"
                   onkeyup="filterTable()">
            <select id="deptFilter" class="hims-input hims-select" style="width:160px;padding:7px 12px;font-size:13px" onchange="filterTable()">
                <option value="">All Departments</option>
                @foreach($employees->pluck('department_name')->unique()->sort() as $dept)
                    <option value="{{ $dept }}">{{ $dept }}</option>
                @endforeach
            </select>
        </div>
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
                <tr class="emp-row"
                    data-name="{{ strtolower($emp->first_name . ' ' . $emp->last_name . ' ' . $emp->employee_code . ' ' . $emp->position_title) }}"
                    data-dept="{{ $emp->department_name }}">
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
                            <form method="POST" action="{{ route('employees.destroy', $emp->employee_id) }}"
                                  onsubmit="return confirm('Delete {{ addslashes($emp->first_name.' '.$emp->last_name) }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;cursor:pointer;border-radius:8px;padding:6px 10px;font-size:12px">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#9ca3af;padding:48px">
                        <div style="font-size:40px;margin-bottom:10px">👥</div>
                        No employees found. <a href="{{ route('employees.create') }}" class="text-primary-hims">Add the first one</a>.
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
@push('scripts')
<script>
function filterTable() {
    const search = document.getElementById('empSearch').value.toLowerCase();
    const dept   = document.getElementById('deptFilter').value;
    document.querySelectorAll('#empTable .emp-row').forEach(row => {
        const matchName = row.dataset.name.includes(search);
        const matchDept = !dept || row.dataset.dept === dept;
        row.style.display = (matchName && matchDept) ? '' : 'none';
    });
}
</script>
@endpush
