@extends('layouts.hims')
@section('title','Departments')
@section('page-title','Departments')
@section('breadcrumb','HIMS / Departments')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Department Directory</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Overview of all hospital departments and their staffing.</p>
    </div>
    {{-- Add Dept button (can be wired up later) --}}
    <button class="btn-hims btn-hims-primary" onclick="document.getElementById('addDeptModal').style.display='flex'">
        <i class="bi bi-plus-circle"></i> Add Department
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-value">{{ count($depts) }}</div>
            <div class="stat-label">Total Departments</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">🏥</div>
            <div class="stat-value">{{ $depts->where('is_clinical',true)->count() }}</div>
            <div class="stat-label">Clinical</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">🗂️</div>
            <div class="stat-value">{{ $depts->where('is_clinical',false)->count() }}</div>
            <div class="stat-label">Administrative</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $depts->sum('employee_count') }}</div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
</div>

{{-- Department cards --}}
<div class="row g-3">
    @forelse($depts as $dept)
    <div class="col-sm-6 col-lg-4">
        <div class="hims-card" style="height:100%;transition:.2s" onmouseover="this.style.boxShadow='var(--hims-shadow-md)'" onmouseout="this.style.boxShadow='var(--hims-shadow)'">
            <div class="card-header" style="padding:16px 20px">
                <h5 style="font-size:14px">
                    <span style="font-size:18px;margin-right:6px">{{ $dept->is_clinical ? '🏥' : '🗂️' }}</span>
                    {{ $dept->name }}
                </h5>
                <span class="hims-badge {{ $dept->is_clinical ? 'blue' : 'gray' }}">
                    {{ $dept->is_clinical ? 'Clinical' : 'Administrative' }}
                </span>
            </div>
            <div class="card-body" style="padding:16px 20px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <div>
                        <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Department Code</div>
                        <div style="font-family:monospace;font-weight:700;font-size:14px;color:var(--hims-primary-dark)">{{ $dept->department_code ?? '—' }}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Staff Count</div>
                        <div style="font-size:22px;font-weight:800;color:var(--hims-text-dark)">{{ $dept->employee_count ?? 0 }}</div>
                    </div>
                </div>

                @if($dept->employee_count > 0)
                <div>
                    <div class="hims-progress" style="height:6px">
                        <div class="hims-progress-bar"
                             style="width:{{ $depts->max('employee_count') > 0 ? round(($dept->employee_count / $depts->max('employee_count')) * 100) : 0 }}%">
                        </div>
                    </div>
                    <div style="font-size:10.5px;color:#9ca3af;margin-top:4px">Relative size</div>
                </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('employees.index') }}?dept={{ urlencode($dept->name) }}"
                       class="btn-hims btn-hims-ghost btn-sm" style="width:100%;justify-content:center">
                        <i class="bi bi-people"></i> View Staff
                    </a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="hims-card">
            <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
                <div style="font-size:48px;margin-bottom:12px">🏢</div>
                <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No departments configured</div>
                <div style="font-size:13px">Run the database seeder to populate departments.</div>
            </div>
        </div>
    </div>
    @endforelse
</div>

{{-- Add Dept Modal --}}
<div id="addDeptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:480px;box-shadow:var(--hims-shadow-lg)">
        <h4 style="font-size:16px;font-weight:700;margin:0 0 20px;color:var(--hims-text-dark)"><i class="bi bi-building-add"></i> Add Department</h4>
        <form method="POST" action="{{ route('departments.store') }}">
            @csrf
            <div class="mb-3">
                <label class="hims-label">Department Name *</label>
                <input type="text" name="name" class="hims-input" required placeholder="e.g. Cardiology">
            </div>
            <div class="mb-3">
                <label class="hims-label">Department Code</label>
                <input type="text" name="department_code" class="hims-input" placeholder="e.g. CAR" maxlength="20">
            </div>
            <div class="mb-4">
                <label class="hims-label">Type</label>
                <select name="is_clinical" class="hims-input hims-select">
                    <option value="1">Clinical</option>
                    <option value="0">Administrative</option>
                </select>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn-hims btn-hims-outline" onclick="document.getElementById('addDeptModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save</button>
            </div>
        </form>
    </div>
</div>

@endsection
