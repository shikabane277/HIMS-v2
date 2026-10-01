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
    @can('manage-employees')
    <button type="button" class="btn-hims btn-hims-primary" data-modal-open="addDeptModal">
        <i class="bi bi-plus-circle"></i> Add Department
    </button>
    @endcan
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-value">{{ count($depts) }}</div>
            <div class="stat-label">Total Departments</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-hospital"></i></div>
            <div class="stat-value">{{ $depts->where('is_clinical',true)->count() }}</div>
            <div class="stat-label">Clinical</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-folder2"></i></div>
            <div class="stat-value">{{ $depts->where('is_clinical',false)->count() }}</div>
            <div class="stat-label">Administrative</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value">{{ $depts->sum('employee_count') }}</div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
</div>

{{-- Department cards --}}
<div class="row g-3">
    @forelse($depts as $dept)
    <div class="col-sm-6 col-lg-4">
        <div class="hims-card" id="department-{{ $dept->department_id }}" style="height:100%;transition:.2s;scroll-margin-top:84px" onmouseover="this.style.boxShadow='var(--hims-shadow-md)'" onmouseout="this.style.boxShadow='var(--hims-shadow)'">
            <div class="card-header" style="padding:16px 20px">
                <h5 style="font-size:14px;display:flex;align-items:center;gap:6px">
                    {!! $dept->is_clinical ? '<i class="bi bi-hospital text-primary-hims"></i>' : '<i class="bi bi-folder2" style="color:#6b7280"></i>' !!}
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

                <div class="mt-3 d-flex gap-2">
                    <a href="{{ route('employees.index') }}?dept={{ urlencode($dept->name) }}"
                       class="btn-hims btn-hims-ghost btn-sm" style="flex:1;justify-content:center">
                        <i class="bi bi-people"></i> Staff
                    </a>
                    @can('manage-employees')
                    <button type="button" class="btn-hims btn-hims-outline btn-sm"
                            data-modal-open="editDeptModal"
                            data-dept-edit
                            data-action="{{ route('departments.update', $dept->department_id) }}"
                            data-name="{{ $dept->name }}"
                            data-code="{{ $dept->department_code ?? '' }}"
                            data-clinical="{{ $dept->is_clinical ? '1' : '0' }}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @if(($dept->employee_count ?? 0) === 0)
                    <form method="POST" action="{{ route('departments.destroy', $dept->department_id) }}"
                          onsubmit="return confirm('Delete department {{ addslashes($dept->name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px 10px;cursor:pointer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="hims-card">
            <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
                <div style="font-size:48px;margin-bottom:12px;color:#9ca3af"><i class="bi bi-building"></i></div>
                <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No departments configured</div>
                <div style="font-size:13px">Run the database seeder to populate departments.</div>
            </div>
        </div>
    </div>
    @endforelse
</div>

@can('manage-employees')
@push('modals')
{{-- Add Dept Modal --}}
<div class="hims-modal-backdrop" id="addDeptModal">
    <div class="hims-modal" style="max-width:480px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-building-add"></i> Add Department</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('departments.store') }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Department Name *</label>
                    <input type="text" name="name" class="hims-input" required placeholder="e.g. Cardiology">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Department Code</label>
                    <input type="text" name="department_code" class="hims-input" placeholder="e.g. CAR" maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Type</label>
                    <select name="is_clinical" class="hims-input hims-select">
                        <option value="1">Clinical</option>
                        <option value="0">Administrative</option>
                    </select>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Dept Modal --}}
<div class="hims-modal-backdrop" id="editDeptModal">
    <div class="hims-modal" style="max-width:480px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Edit Department</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="editDeptForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Department Name *</label>
                    <input type="text" name="name" id="edit_dept_name" class="hims-input" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Department Code</label>
                    <input type="text" name="department_code" id="edit_dept_code" class="hims-input" maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Type</label>
                    <select name="is_clinical" id="edit_dept_clinical" class="hims-input hims-select">
                        <option value="1">Clinical</option>
                        <option value="0">Administrative</option>
                    </select>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-dept-edit]');
    if (!btn) return;
    const form = document.getElementById('editDeptForm');
    form.action = btn.dataset.action;
    document.getElementById('edit_dept_name').value = btn.dataset.name;
    document.getElementById('edit_dept_code').value = btn.dataset.code;
    document.getElementById('edit_dept_clinical').value = btn.dataset.clinical;
});
</script>
@endpush
@endcan

@endsection
