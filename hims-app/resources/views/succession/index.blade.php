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
    <div class="d-flex gap-2">
        <a href="{{ route('succession.positions.create') }}" class="btn-hims btn-hims-outline"><i class="bi bi-briefcase"></i> Add Critical Position</a>
        <a href="{{ route('succession.candidates.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-person-plus"></i> Nominate Candidate</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🏥</div><div class="stat-value">{{ $stats['critical_positions'] ?? 0 }}</div><div class="stat-label">Critical Positions</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🌟</div><div class="stat-value">{{ $stats['ready_now'] ?? 0 }}</div><div class="stat-label">Ready Now</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📈</div><div class="stat-value">{{ $stats['in_development'] ?? 0 }}</div><div class="stat-label">In Development</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🔴</div><div class="stat-value">{{ $stats['high_risk'] ?? 0 }}</div><div class="stat-label">High-Risk Vacancies</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- 9-Box Grid -->
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-grid-3x3-gap"></i> 9-Box Talent Grid</h5>
                <div style="display:flex;gap:12px;align-items:center">
                    <span style="font-size:11px;color:#6b7280">↑ Performance  → Potential</span>
                </div>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;grid-template-rows:1fr 1fr 1fr;gap:6px;height:260px">
                    @php
                        $boxes = [
                            ['label'=>'Future Star','class'=>'star','perf'=>'High','pot'=>'High'],
                            ['label'=>'High Performer','class'=>'high','perf'=>'High','pot'=>'Medium'],
                            ['label'=>'Solid Contributor','class'=>'solid','perf'=>'High','pot'=>'Low'],
                            ['label'=>'High Potential','class'=>'potential','perf'=>'Medium','pot'=>'High'],
                            ['label'=>'Core Employee','class'=>'core','perf'=>'Medium','pot'=>'Medium'],
                            ['label'=>'Average','class'=>'avg','perf'=>'Medium','pot'=>'Low'],
                            ['label'=>'Rough Diamond','class'=>'diamond','perf'=>'Low','pot'=>'High'],
                            ['label'=>'Inconsistent','class'=>'inconsist','perf'=>'Low','pot'=>'Medium'],
                            ['label'=>'Underperformer','class'=>'under','perf'=>'Low','pot'=>'Low'],
                        ];
                        $boxCounts = $box_counts ?? [];
                    @endphp
                    @foreach($boxes as $box)
                    <div class="nine-box-cell {{ $box['class'] }}"
                         title="{{ $box['perf'] }} Performance / {{ $box['pot'] }} Potential">
                        <span class="nine-box-count">{{ $boxCounts[$box['class']] ?? 0 }}</span>
                        <span style="font-size:10px;text-align:center;line-height:1.2">{{ $box['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:10.5px;color:#6b7280">
                    <span>← Low Potential</span><span>High Potential →</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Critical Positions -->
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-exclamation-triangle-fill"></i> Critical Positions</h5>
                <a href="{{ route('succession.positions.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Position</th><th>Dept</th><th>Candidates</th><th>Risk</th></tr></thead>
                    <tbody>
                        @forelse($positions ?? [] as $pos)
                        <tr>
                            <td>
                                <a href="{{ route('succession.positions.show', $pos->position_id) }}" style="font-weight:600;color:var(--hims-text-dark)">{{ $pos->position_title }}</a>
                                <div style="font-size:11px;color:#9ca3af">{{ $pos->current_holder_name ?? 'Vacant' }}</div>
                            </td>
                            <td style="font-size:12.5px">{{ $pos->department_name ?? '—' }}</td>
                            <td>
                                <span class="hims-badge {{ $pos->candidates_count > 0 ? 'green' : 'red' }}">
                                    {{ $pos->candidates_count ?? 0 }} candidates
                                </span>
                            </td>
                            <td>
                                <span class="hims-badge {{ $pos->vacancy_risk === 'critical' ? 'red' : ($pos->vacancy_risk === 'high' ? 'yellow' : 'green') }}">
                                    {{ ucfirst($pos->vacancy_risk) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:32px">No critical positions defined yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Candidate Pipeline -->
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-people-fill"></i> Succession Candidate Pipeline</h5>
        <div class="d-flex gap-2">
            <select class="hims-input hims-select" style="width:170px;padding:6px 12px;font-size:13px">
                <option>All Positions</option>
                @foreach($positions ?? [] as $pos)
                <option>{{ $pos->position_title }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Candidate</th><th>Target Position</th><th>9-Box</th><th>Readiness</th><th>Dev Progress</th><th>Status</th><th>Actions</th></tr></thead>
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
                    <td>
                        @php
                            $boxClass = match($cand->nine_box_label ?? '') {
                                'star' => 'green', 'high' => 'green', 'solid' => 'green',
                                'potential', 'core' => 'blue', 'diamond' => 'yellow',
                                'under', 'inconsist' => 'red', default => 'gray'
                            };
                        @endphp
                        <span class="hims-badge {{ $boxClass }}">{{ ucfirst(str_replace('_',' ',$cand->nine_box_label ?? '—')) }}</span>
                    </td>
                    <td>
                        <span class="hims-badge {{ $cand->readiness_level === 'ready_now' ? 'green' : ($cand->readiness_level === '1_2_years' ? 'yellow' : 'gray') }}">
                            {{ str_replace('_',' ',ucfirst($cand->readiness_level ?? '—')) }}
                        </span>
                    </td>
                    <td>
                        <div style="min-width:100px">
                            <div style="font-size:11px;color:#6b7280;margin-bottom:3px">{{ $cand->dev_progress ?? 0 }}%</div>
                            <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $cand->dev_progress ?? 0 }}%"></div></div>
                        </div>
                    </td>
                    <td><span class="hims-badge {{ $cand->status === 'approved' ? 'green' : 'yellow' }}">{{ ucfirst($cand->status ?? 'proposed') }}</span></td>
                    <td><a href="{{ route('succession.candidates.show', $cand->candidate_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:32px">No candidates nominated yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
