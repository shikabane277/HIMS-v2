@extends('layouts.hims')
@section('title','AI Gap Analysis')
@section('page-title','AI-Assisted Competency Gap Analysis')
@section('breadcrumb','HIMS / Competency / Gap Analysis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">AI-Assisted Competency Gap Analysis</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Combines performance results, competency assessments against job requirements, and training received
            to identify missing skills and suggest improvements.
        </p>
    </div>
    <a href="{{ route('competency.gap.department', request()->only('department')) }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-robot"></i> Run Department Analysis
    </a>
</div>

@if($departments->isNotEmpty())
<div class="hims-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('competency.gap.index') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:220px">
                <label class="hims-label">Department</label>
                <select name="department" class="hims-input hims-select">
                    <option value="">— All departments —</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->department_id }}" @selected($departmentId === $dept->department_id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-hims btn-hims-outline"><i class="bi bi-funnel"></i> Filter</button>
        </form>
    </div>
</div>
@endif

{{-- Department-level weak spots (computed from the database, no AI call) --}}
<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-graph-down-arrow"></i> Weakest Competencies {{ $department['department']->name ?? '— whole organisation' }}</h5>
        <span class="hims-badge blue">{{ $department['headcount'] }} active staff</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Competency</th><th>Required</th><th>Avg Proficiency</th><th>Avg Gap</th><th>Below Requirement</th><th>Suggested Response</th></tr>
            </thead>
            <tbody>
                @forelse($department['weakest'] as $row)
                <tr>
                    <td>
                        <strong>{{ $row->competency_name }}</strong>
                        @if($row->is_mandatory)<span class="hims-badge red" style="margin-left:6px">Mandatory</span>@endif
                    </td>
                    <td>{{ $row->required_proficiency }}/5</td>
                    <td>{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                    <td><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                    <td>{{ $row->employees_below }} of {{ $row->assessed_employees }}</td>
                    <td style="font-size:12px;color:#6b7280">
                        {{ abs((float) $row->avg_gap) >= 2 ? 'Instructor-led workshop with supervised practice' : 'Refresher module plus reassessment' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    No measured gaps. Either every assessed competency meets its requirement, or no assessments have been recorded yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Per-employee entry point --}}
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-person-lines-fill"></i> Analyse an Individual</h5>
        <span style="font-size:12px;color:#9ca3af">{{ $employees->count() }} employee{{ $employees->count() === 1 ? '' : 's' }} in scope</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Position</th><th>Department</th><th style="text-align:right">Analysis</th></tr></thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                    <td>{{ $employee->position_title ?? '—' }}</td>
                    <td>{{ $employee->department_name }}</td>
                    <td style="text-align:right">
                        <a href="{{ route('competency.gap.employee', $employee->employee_id) }}" class="btn-hims btn-hims-primary btn-sm">
                            <i class="bi bi-robot"></i> Analyse
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:32px">No employees in your scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
