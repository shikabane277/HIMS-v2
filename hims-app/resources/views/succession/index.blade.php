@extends('layouts.hims')
@section('title','Succession Planning')
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Succession Planning</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">9-Box talent grid, critical role management, and leadership pipelines.</p>
    </div>
    @can('manage-succession')
    <div class="d-flex gap-2">
        <a href="{{ route('succession.positions.create') }}" class="btn-hims btn-hims-outline"><i class="bi bi-briefcase"></i> Add Critical Position</a>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="nominationModal"><i class="bi bi-person-plus"></i> Nominate Successor</button>
    </div>
    @endcan
</div>

@if($canSeeConfidential)
<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">🏥</div><div class="stat-value">{{ $stats['critical_positions'] ?? 0 }}</div><div class="stat-label">Critical Positions</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">🌟</div><div class="stat-value">{{ $stats['ready_now'] ?? 0 }}</div><div class="stat-label">Ready Now</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">📈</div><div class="stat-value">{{ $stats['in_development'] ?? 0 }}</div><div class="stat-label">In Development</div></div></div>
</div>
@else
<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-briefcase"></i></div><div class="stat-value">{{ $stats['critical_positions'] ?? 0 }}</div><div class="stat-label">Relevant Critical Positions</div></div></div>
    <div class="col-sm-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-signpost-split"></i></div><div class="stat-value">{{ $candidates->count() }}</div><div class="stat-label">Direct-Report Development Plans</div></div></div>
</div>
@endif

<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-exclamation-triangle-fill"></i> Critical Positions & Coverage</h5>
        <a href="{{ route('succession.positions.index') }}" class="btn-hims btn-hims-ghost btn-sm">All Positions</a>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Position</th><th>Department</th><th>Current Holder</th>@if($canSeeConfidential)<th>Nominated Candidates</th>@endif</tr></thead>
            <tbody>
                @forelse($positions ?? [] as $pos)
                <tr>
                    <td>
                        <a href="{{ route('succession.positions.show', $pos->position_id) }}" style="font-weight:600;color:var(--hims-text-dark)">{{ $pos->position_title }}</a>
                    </td>
                    <td style="font-size:12.5px">{{ $pos->department_name ?? '—' }}</td>
                    <td style="font-size:12.5px">{{ $pos->current_holder_name ?? 'Vacant' }}</td>
                    @if($canSeeConfidential)<td>
                        <span class="hims-badge {{ $pos->candidates_count > 0 ? 'green' : 'red' }}">
                            {{ $pos->candidates_count ?? 0 }} candidate(s)
                        </span>
                    </td>@endif
                </tr>
                @empty
                <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:32px">No critical positions defined yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Candidate Pipeline -->
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-people-fill"></i> Succession Candidate Pipeline</h5>
        <div class="d-flex gap-2">
            <form method="GET" action="{{ route('succession.index') }}">
                <select name="position_id" onchange="this.form.submit()"
                        class="hims-input hims-select" style="width:190px;padding:6px 12px;font-size:13px">
                    <option value="">All Positions</option>
                    @foreach($positions ?? [] as $pos)
                    <option value="{{ $pos->position_id }}" @selected(($filterPositionId ?? null) === $pos->position_id)>
                        {{ $pos->position_title }}
                    </option>
                    @endforeach
                </select>
            </form>
            @if($filterPositionId ?? null)
            <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost btn-sm" title="Clear filter">
                <i class="bi bi-x-lg"></i>
            </a>
            @endif
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Candidate</th><th>Target Position</th>@if($canSeeConfidential)<th>Readiness</th>@endif<th>Dev Progress</th>@if($canSeeConfidential)<th>Status</th>@endif<th>Actions</th></tr></thead>
            <tbody>
                @forelse($candidates ?? [] as $cand)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <div style="width:32px;height:32px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--hims-primary-dark)">
                                {{ strtoupper(substr($cand->first_name ?? 'U',0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px">{{ ($cand->first_name??'') . ' ' . ($cand->last_name??'') }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $cand->position_title_current ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px">{{ $cand->target_position ?? '—' }}</td>
                    @if($canSeeConfidential)<td>
                        <span class="hims-badge {{ $cand->readiness_level === 'ready_now' ? 'green' : ($cand->readiness_level === '1_2_years' ? 'yellow' : 'gray') }}">
                            {{ str_replace('_',' ',ucfirst($cand->readiness_level ?? '—')) }}
                        </span>
                    </td>@endif
                    <td>
                        <div style="min-width:100px">
                            <div style="font-size:11px;color:#6b7280;margin-bottom:3px">{{ $cand->dev_progress ?? 0 }}%</div>
                            <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $cand->dev_progress ?? 0 }}%"></div></div>
                        </div>
                    </td>
                    @if($canSeeConfidential)<td><span class="hims-badge {{ $cand->status === 'approved' ? 'green' : 'yellow' }}">{{ ucfirst($cand->status ?? 'proposed') }}</span></td>@endif
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('succession.candidates.show', $cand->candidate_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                            @can('manage-succession')
                            <a href="{{ route('succession.candidates.edit', $cand->candidate_id) }}" class="btn-hims btn-hims-ghost btn-sm" title="Edit nomination">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    {{ ($filterPositionId ?? null) ? 'No candidates nominated for this position.' : 'No candidates nominated yet.' }}
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('manage-succession')
@push('modals')
<div class="hims-modal-backdrop" id="nominationModal" style="display:none">
    <div class="hims-modal" style="max-width:520px">
        <div class="hims-modal-header">
            <h4><i class="bi bi-person-plus"></i> Nominate Successor</h4>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('succession.candidates.store') }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Candidate</label>
                    <select name="employee_id" class="hims-input hims-select" required>
                        <option value="">Select Employee...</option>
                        @foreach($employees ?? [] as $emp)
                            <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->position_title ?? 'Employee' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Target Position</label>
                    <select name="position_id" class="hims-input hims-select" required>
                        <option value="">Select Target Position...</option>
                        @foreach($positions ?? [] as $pos)
                            <option value="{{ $pos->position_id }}">{{ $pos->position_title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Readiness Stage</label>
                    <select name="readiness_level" class="hims-input hims-select" required>
                        <option value="ready_now">Ready Now</option>
                        <option value="1_2_years" selected>Ready in 1–2 Years</option>
                        <option value="2_5_years">Ready in 2–5 Years</option>
                        <option value="long_term">Long Term</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Assigned Mentor (Optional)</label>
                    <select name="mentor_id" class="hims-input hims-select">
                        <option value="">None</option>
                        @foreach($employees ?? [] as $emp)
                            <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Notes</label>
                    <textarea name="notes" class="hims-input" rows="3" placeholder="Nomination rationale or initial goals..."></textarea>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-ghost" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary">Submit Nomination</button>
            </div>
        </form>
    </div>
</div>
@endpush
@endcan
@endsection
