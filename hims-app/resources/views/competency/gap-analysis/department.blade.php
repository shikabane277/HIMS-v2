@extends('layouts.hims')
@section('title','Department Gap Analysis')
@section('page-title','AI-Assisted Competency Gap Analysis')
@section('breadcrumb','HIMS / Competency / Gap Analysis / Department')

@section('content')
@php $ai = $analysis['ai']; @endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">
            {{ $analysis['department']->name ?? 'Whole Organisation' }} — Workforce Gap Analysis
        </h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $analysis['headcount'] }} active staff · generated {{ $analysis['generated_at']->diffForHumans() }}
        </p>
    </div>
    <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="hims-card mb-4" style="border-left:4px solid var(--hims-primary)">
    <div class="card-header"><h5><i class="bi bi-robot"></i> AI Workforce Assessment</h5></div>
    <div class="card-body">
        @if(! $ai)
            <div style="color:#9ca3af;font-size:13px">
                No measured gaps to analyse — the AI layer is skipped when there is nothing below requirement.
            </div>
        @elseif(! empty($ai['unavailable']))
            <div class="hims-alert error" style="margin:0">
                <i class="bi bi-exclamation-circle-fill"></i> {{ $ai['message'] ?? 'AI analysis unavailable.' }}
                <div style="font-size:12px;margin-top:6px;color:#6b7280">
                    The measured table below is computed from the database and is unaffected.
                </div>
            </div>
        @else
            @if(!empty($ai['headline']))
            <p style="font-size:14.5px;font-weight:600;line-height:1.6;margin-bottom:16px">{{ $ai['headline'] }}</p>
            @endif

            @if(!empty($ai['patient_safety_note']))
            <div class="hims-alert" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;margin-bottom:16px">
                <i class="bi bi-shield-exclamation"></i> <strong>Patient safety:</strong> {{ $ai['patient_safety_note'] }}
            </div>
            @endif

            @if(!empty($ai['themes']))
            <div class="mb-3">
                <div class="hims-label" style="margin-bottom:8px">Themes</div>
                <div class="row g-2">
                    @foreach($ai['themes'] as $theme)
                    <div class="col-md-6">
                        <div style="padding:12px 14px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px;height:100%">
                            <strong style="font-size:13px">{{ $theme['theme'] ?? '—' }}</strong>
                            <div style="font-size:12px;color:#4b5563;margin-top:5px;line-height:1.6">{{ $theme['explanation'] ?? '' }}</div>
                            @if(!empty($theme['competencies']))
                            <div style="margin-top:7px;display:flex;gap:4px;flex-wrap:wrap">
                                @foreach((array) $theme['competencies'] as $competency)
                                <span class="hims-badge blue">{{ $competency }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($ai['recommended_actions']))
            <div class="hims-label" style="margin-bottom:8px">Recommended Actions</div>
            <table class="hims-table">
                <thead><tr><th>Action</th><th>Rationale</th><th>Timeframe</th><th>Owner</th></tr></thead>
                <tbody>
                    @foreach($ai['recommended_actions'] as $action)
                    <tr>
                        <td><strong>{{ $action['action'] ?? '—' }}</strong></td>
                        <td style="font-size:12px;color:#6b7280">{{ $action['rationale'] ?? '—' }}</td>
                        <td style="font-size:12px">{{ $action['timeframe'] ?? '—' }}</td>
                        <td style="font-size:12px">{{ $action['owner'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-graph-down-arrow"></i> Measured Weakest Competencies</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr><th>Competency</th><th>Required</th><th>Avg</th><th>Avg Gap</th><th>Below</th><th>Assessed</th></tr>
                    </thead>
                    <tbody>
                        @forelse($analysis['weakest'] as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->competency_name }}</strong>
                                @if($row->is_mandatory)<span class="hims-badge red" style="margin-left:6px">Mandatory</span>@endif
                            </td>
                            <td>{{ $row->required_proficiency }}/5</td>
                            <td>{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                            <td><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                            <td>{{ $row->employees_below }}</td>
                            <td>{{ $row->assessed_employees }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                            No measured gaps in scope.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-mortarboard"></i> Training Demand</h5></div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($analysis['training_demand'] as $demand)
                <div style="padding:11px 13px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px">
                    <strong style="font-size:13px">{{ $demand['competency_name'] }}</strong>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">
                        {{ $demand['employees_below'] }} staff below requirement (avg gap {{ number_format($demand['avg_gap'], 2) }})
                    </div>
                    <div style="font-size:12px;color:var(--hims-primary);margin-top:5px;font-weight:600">
                        {{ $demand['suggested_format'] }}
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No training demand identified.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
