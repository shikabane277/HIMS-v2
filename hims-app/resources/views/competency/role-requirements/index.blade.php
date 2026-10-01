@extends('layouts.hims')
@section('title','Role Competency Requirements')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / Role Requirements')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Role Competency Requirements</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Define mandatory and expected minimum proficiency levels for each clinical and administrative role.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-ghost">
            <i class="bi bi-arrow-left"></i> Back to Competency
        </a>
        @can('manage-competency-framework')
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="roleReqCreateModal">
            <i class="bi bi-plus-circle"></i> Add Requirement
        </button>
        @endcan
    </div>
</div>

{{-- Role filter tabs / selector --}}
<div class="hims-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('competency.role-requirements.index') }}" class="d-flex align-items-center gap-3" style="flex-wrap:wrap">
            <label class="hims-label mb-0" for="roleSelect" style="white-space:nowrap">Select Job Role:</label>
            <select name="role_id" id="roleSelect" class="hims-input hims-select" style="max-width:320px" onchange="this.form.submit()">
                @foreach($roles as $role)
                    <option value="{{ $role->role_id }}" @selected($selectedRole === $role->role_id)>
                        {{ $role->role_name }}
                    </option>
                @endforeach
            </select>
            <span class="text-muted" style="font-size:13px">{{ $requirements->count() }} requirements configured</span>
        </form>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-shield-check"></i> Required Competencies for Selected Role</h5>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Competency</th>
                    <th>Code</th>
                    <th>Minimum Proficiency</th>
                    <th>Criticality</th>
                    <th>Global Standard</th>
                    @can('manage-competency-framework')
                    <th>Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse($requirements as $req)
                <tr>
                    <td data-label="Competency"><strong>{{ $req->competency_name }}</strong></td>
                    <td data-label="Code" style="font-family:monospace;font-size:12.5px;color:#6b7280">{{ $req->competency_code }}</td>
                    <td data-label="Minimum Proficiency">
                        <span class="hims-badge blue" style="font-weight:700">Level {{ $req->minimum_proficiency }} / 5</span>
                    </td>
                    <td data-label="Criticality">
                        @if($req->is_critical)
                            <span class="hims-badge red"><i class="bi bi-exclamation-triangle-fill"></i> Critical</span>
                        @else
                            <span class="hims-badge gray">Standard</span>
                        @endif
                    </td>
                    <td data-label="Global Standard" style="color:#6b7280;font-size:13px">
                        Level {{ $req->global_required ?? '—' }}
                    </td>
                    @can('manage-competency-framework')
                    <td data-label="Actions">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-hims btn-hims-outline btn-sm"
                                    data-modal-open="roleReqEditModal"
                                    data-req-edit
                                    data-action="{{ route('competency.role-requirements.update', $req->id) }}"
                                    data-name="{{ $req->competency_name }}"
                                    data-proficiency="{{ $req->minimum_proficiency }}"
                                    data-critical="{{ $req->is_critical ? '1' : '0' }}">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('competency.role-requirements.destroy', $req->id) }}"
                                  onsubmit="return confirm('Remove requirement for {{ addslashes($req->competency_name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px 10px;cursor:pointer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    @endcan
                </tr>
                @empty
                <tr>
                    <td colspan="{{ auth()->user()->can('manage-competency-framework') ? 6 : 5 }}" class="text-center" style="color:#9ca3af;padding:48px">
                        <div style="font-size:36px;margin-bottom:10px">📋</div>
                        No competency requirements configured for this role yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('manage-competency-framework')
{{-- Create Modal --}}
@push('modals')
<div class="hims-modal-backdrop" id="roleReqCreateModal">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5><i class="bi bi-plus-circle"></i> Add Role Requirement</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('competency.role-requirements.store') }}">
            @csrf
            <input type="hidden" name="role_id" value="{{ $selectedRole }}">
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Competency *</label>
                    <select name="competency_id" class="hims-input hims-select" required>
                        <option value="">— Select Competency —</option>
                        @foreach($competencies as $c)
                            <option value="{{ $c->competency_id }}">{{ $c->competency_name }} ({{ $c->competency_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Minimum Required Proficiency (1–5) *</label>
                    <select name="minimum_proficiency" class="hims-input hims-select" required>
                        <option value="1">Level 1 — Novice / Fundamental Awareness</option>
                        <option value="2">Level 2 — Developing / Supervised Practice</option>
                        <option value="3" selected>Level 3 — Proficient / Independent Practice</option>
                        <option value="4">Level 4 — Advanced / Expert Practice</option>
                        <option value="5">Level 5 — Master / Clinical Leadership</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="d-flex align-items-center gap-2" style="font-size:13.5px;cursor:pointer">
                        <input type="checkbox" name="is_critical" value="1">
                        <strong>Mark as Critical Competency</strong>
                    </label>
                    <small style="color:#6b7280;display:block;margin-top:4px">Critical gaps receive high priority in AI gap analysis and development plans.</small>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Requirement</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="hims-modal-backdrop" id="roleReqEditModal">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Edit Role Requirement</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="roleReqEditForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Competency</label>
                    <input type="text" id="edit_comp_name" class="hims-input" readonly disabled style="background:#f1f5f9">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Minimum Required Proficiency (1–5) *</label>
                    <select name="minimum_proficiency" id="edit_proficiency" class="hims-input hims-select" required>
                        <option value="1">Level 1 — Novice / Fundamental Awareness</option>
                        <option value="2">Level 2 — Developing / Supervised Practice</option>
                        <option value="3">Level 3 — Proficient / Independent Practice</option>
                        <option value="4">Level 4 — Advanced / Expert Practice</option>
                        <option value="5">Level 5 — Master / Clinical Leadership</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="d-flex align-items-center gap-2" style="font-size:13.5px;cursor:pointer">
                        <input type="checkbox" name="is_critical" id="edit_critical" value="1">
                        <strong>Mark as Critical Competency</strong>
                    </label>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Update</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-req-edit]');
    if (!btn) return;
    const form = document.getElementById('roleReqEditForm');
    form.action = btn.dataset.action;
    document.getElementById('edit_comp_name').value = btn.dataset.name;
    document.getElementById('edit_proficiency').value = btn.dataset.proficiency;
    document.getElementById('edit_critical').checked = btn.dataset.critical === '1';
});
</script>
@endpush
@endcan

@endsection
