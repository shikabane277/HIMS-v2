@extends('layouts.hims')
@section('title',$position->position_title)
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Position')
@section('content')

@php
    $risk = strtolower((string) $position->vacancy_risk);
    $riskColour = match($risk) {
        'critical', 'high' => 'red',
        'medium'           => 'yellow',
        'low'              => 'green',
        default            => 'gray',
    };
    $readinessLabels = [
        'ready_now'  => 'Ready Now',
        '1_2_years'  => 'Ready in 1–2 Years',
        '2_5_years'  => 'Ready in 2–5 Years',
        'long_term'  => 'Long Term',
    ];
    $nineBoxLabels = [
        'star' => 'Star', 'high' => 'High Performer', 'solid' => 'Solid Performer',
        'potential' => 'High Potential', 'core' => 'Core Player', 'avg' => 'Average Performer',
        'diamond' => 'Rough Diamond', 'inconsist' => 'Inconsistent', 'under' => 'Underperformer',
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $position->position_title }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $position->department_name }} ·
            <span class="hims-badge {{ $riskColour }}">{{ ucfirst($risk ?: 'unknown') }} vacancy risk</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('succession.positions.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> All Positions</a>
        @can('manage-succession')
        <a href="{{ route('succession.candidates.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-person-plus"></i> Nominate Candidate</a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="bi bi-person-badge"></i></div>
            <div class="stat-value" style="font-size:16px">{{ trim((string) $position->current_holder_name) ?: 'Vacant' }}</div>
            <div class="stat-label">Current Holder</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-people"></i></div>
            <div class="stat-value">{{ $candidates->count() }}</div>
            <div class="stat-label">Successors in Pipeline</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-lightning-charge"></i></div>
            <div class="stat-value">{{ $candidates->where('readiness_level','ready_now')->count() }}</div>
            <div class="stat-label">Ready Now</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-value" style="font-size:16px">
                {{ $position->estimated_vacancy_date ? \Carbon\Carbon::parse($position->estimated_vacancy_date)->format('M Y') : '—' }}
            </div>
            <div class="stat-label">Est. Vacancy</div>
        </div>
    </div>
</div>

@if($position->impact_description)
<div class="hims-card mb-3">
    <div class="card-header"><h5><i class="bi bi-exclamation-triangle"></i> Impact if Vacant</h5></div>
    <div class="card-body"><p style="margin:0;color:#374151;font-size:13.5px">{{ $position->impact_description }}</p></div>
</div>
@endif

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-people-fill"></i> Succession Pipeline</h5>
        <span style="font-size:11.5px;color:#9ca3af">9-box placement from performance × potential</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Readiness</th>
                    <th>Performance</th>
                    <th>Potential</th>
                    <th>9-Box</th>
                    <th>Status</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($candidates as $candidate)
                <tr>
                    <td><strong>{{ $candidate->employee_name }}</strong></td>
                    <td>
                        <span class="hims-badge {{ $candidate->readiness_level === 'ready_now' ? 'green' : 'blue' }}">
                            {{ $readinessLabels[$candidate->readiness_level] ?? ucfirst(str_replace('_',' ', (string) $candidate->readiness_level)) }}
                        </span>
                    </td>
                    <td><span class="gap-chip {{ (int) $candidate->performance_score >= 4 ? 'positive' : 'negative' }}">{{ $candidate->performance_score }}/5</span></td>
                    <td><span class="gap-chip {{ (int) $candidate->potential_score >= 4 ? 'positive' : 'negative' }}">{{ $candidate->potential_score }}/5</span></td>
                    <td>{{ $nineBoxLabels[$candidate->nine_box_label] ?? ($candidate->nine_box_label ?? '—') }}</td>
                    <td><span class="hims-badge gray">{{ ucfirst(str_replace('_',' ', (string) $candidate->status)) }}</span></td>
                    <td>
                        <a href="{{ route('succession.candidates.show', $candidate->candidate_id) }}" class="btn-hims btn-hims-outline btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:36px">
                    No successors identified for this position — that is itself a continuity risk.
                    @can('manage-succession')
                    <div class="mt-2"><a href="{{ route('succession.candidates.create') }}" class="btn-hims btn-hims-primary btn-sm">Nominate someone</a></div>
                    @endcan
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
