@extends('layouts.hims')
@section('title','Employees')
@section('page-title','Employees')
@section('breadcrumb','HIMS / Employees')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Employee Directory</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Hospital staff directory synchronized from HR1 (Employee Master) & HR2 (Hospital Operations).
        </p>
        <div class="d-inline-flex align-items-center gap-2 mt-2 px-3 py-1" style="background:rgba(22,163,74,0.08);border:1px solid var(--hims-border);border-radius:20px;font-size:12px;color:var(--hims-primary-dark);font-weight:600">
            <i class="bi bi-link-45deg" style="font-size:15px"></i> Sourced from HR1 & HR2 &bull; Direct Add/Remove Disabled
        </div>
    </div>
    @can('manage-employees')
    <div class="d-flex gap-2">
        <a href="{{ route('employees.manager-setup') }}" class="btn-hims btn-hims-outline">
            <i class="bi bi-person-check-fill"></i> Manager Setup
        </a>
    </div>
    @endcan
</div>

{{-- Protected Employee Directory & Workforce Analytics --}}
<div class="hims-sensitive-wrapper">
    <div class="hims-sensitive-records {{ ! \App\Support\RecordAccess::isUnlocked() ? 'is-blurred' : '' }}">

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value">{{ $employees->total() }}</div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-value">{{ $employees->where('employment_status','active')->count() }}</div>
            <div class="stat-label">Active Staff</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-value">{{ $departments->count() }}</div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clipboard-data"></i></div>
            <div class="stat-value">{{ $employees->pluck('role_name')->unique()->count() }}</div>
            <div class="stat-label">Roles on This Page</div>
        </div>
    </div>
</div>

{{-- Workforce Analytics & Trends --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="hims-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 style="margin:0"><i class="bi bi-building text-primary-hims"></i> Department Staffing Distribution</h5>
                <span class="badge" style="background:var(--hims-primary-xlight);color:var(--hims-primary-dark);font-size:11px;font-weight:600">Top Departments</span>
            </div>
            <div class="card-body" style="padding:16px">
                <div style="position:relative;height:220px">
                    <canvas id="deptDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hims-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 style="margin:0"><i class="bi bi-person-badge text-primary-hims"></i> Employment Status</h5>
                <span class="badge" style="background:rgba(2, 132, 199, 0.1);color:#0284c7;font-size:11px;font-weight:600">Staff Health</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="padding:16px">
                <div style="position:relative;height:220px;width:100%">
                    <canvas id="statusDistributionChart"></canvas>
                </div>
            </div>
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
                    <td data-label="Employee">
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
                    <td data-label="Code" style="font-size:12.5px;color:#6b7280;font-family:monospace">{{ $emp->employee_code }}</td>
                    <td data-label="Department">
                        <span class="hims-badge green">{{ $emp->department_name }}</span>
                    </td>
                    <td data-label="Role" style="font-size:13px">{{ $emp->role_name }}</td>
                    <td data-label="Hire Date" style="font-size:12.5px;color:#6b7280">
                        {{ \Carbon\Carbon::parse($emp->hire_date)->format('M d, Y') }}
                    </td>
                    <td data-label="Status">
                        <span class="hims-badge {{ $emp->employment_status === 'active' ? 'green' : 'gray' }}">
                            <span class="status-dot {{ $emp->employment_status === 'active' ? 'active' : 'inactive' }}"></span>
                            {{ ucfirst($emp->employment_status) }}
                        </span>
                    </td>
                    <td data-label="Actions">
                        <div style="display:flex;gap:6px;align-items:center">
                            <a href="{{ route('employees.show', $emp->employee_id) }}" class="btn-hims btn-hims-ghost btn-sm">
                                <i class="bi bi-eye"></i> View
                            </a>
                            @can('manage-employees')
                            <a href="{{ route('employees.edit', $emp->employee_id) }}" class="btn-hims btn-hims-outline btn-sm" title="Edit employee details">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center" style="color:#9ca3af;padding:48px">
                        <div style="font-size:40px;margin-bottom:10px;color:#9ca3af"><i class="bi bi-people"></i></div>
                        @if(($filters['q'] ?? '') !== '' || ($filters['department'] ?? null))
                            No employees match that filter.
                            <a href="{{ route('employees.index') }}" class="text-primary-hims">Clear the filter</a>.
                        @else
                            No employees found. Please check external HR1/HR2 synchronization.
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

    </div>
    @if(! \App\Support\RecordAccess::isUnlocked())
        @include('partials.record-access-overlay', ['slot' => 'Hospital personnel and workforce directory records are protected. Enter the Record Access Password to view employees.'])
    @endif
</div>

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    // Department Headcount Distribution Chart
    const deptCtx = document.getElementById('deptDistributionChart');
    if (deptCtx) {
        new Chart(deptCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($deptChartData['labels'] ?? []),
                datasets: [{
                    label: 'Staff Count',
                    data: @json($deptChartData['counts'] ?? []),
                    backgroundColor: 'rgba(5, 150, 105, 0.85)',
                    borderColor: '#059669',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.raw + ' employee(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Employment Status Distribution Chart
    const statusCtx = document.getElementById('statusDistributionChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json($statusChartData['labels'] ?? []),
                datasets: [{
                    data: @json($statusChartData['counts'] ?? []),
                    backgroundColor: [
                        '#059669',
                        '#0284c7',
                        '#f59e0b',
                        '#8b5cf6',
                        '#64748b',
                        '#ef4444'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: { size: 11.5, family: 'Inter, system-ui, sans-serif' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.raw + ' staff';
                            }
                        }
                    }
                },
                cutout: '62%'
            }
        });
    }
});
</script>
@endpush
@endsection
