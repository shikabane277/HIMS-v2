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
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center" style="color:#9ca3af;padding:48px">
                        <div style="font-size:36px;margin-bottom:10px">📋</div>
                        No competency requirements configured for this role yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
