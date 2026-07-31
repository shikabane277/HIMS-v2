@extends('layouts.hims')
@section('title','Review Detail')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Review')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Reviews</a>
    @php $sc = match($review->status ?? '') { 'approved','archived'=>'green','draft','self_assessment'=>'gray','ai_audit','pending_approval'=>'blue', default=>'yellow' }; @endphp
    <span class="hims-badge {{ $sc }}" style="font-size:13px;padding:6px 14px">{{ ucfirst(str_replace('_',' ',$review->status ?? 'draft')) }}</span>
</div>

<div class="row g-3">
    {{-- Info card --}}
    <div class="col-lg-4">
        <div class="hims-card mb-3">
            <div style="padding:24px;text-align:center;background:linear-gradient(135deg,var(--hims-primary-pale),var(--hims-primary-xlight));border-bottom:1px solid var(--hims-border)">
                <div style="width:64px;height:64px;background:var(--hims-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:#fff;margin:0 auto 12px">
                    {{ strtoupper(substr($review->first_name ?? 'U',0,1)) }}
                </div>
                <div style="font-size:17px;font-weight:800">{{ $review->first_name }} {{ $review->last_name }}</div>
                <div style="font-size:12.5px;color:#6b7280;margin-top:3px">{{ $review->position_title }}</div>
            </div>
            <div class="card-body">
                <table style="width:100%;font-size:13px">
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280;width:45%">Cycle</td>
                        <td style="padding:9px 0;font-weight:600">{{ $review->cycle_name }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Type</td>
                        <td style="padding:9px 0">{{ ucfirst(str_replace('_',' ',$review->cycle_type ?? '')) }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Self Rating</td>
                        <td style="padding:9px 0;font-weight:700;color:var(--hims-primary)">{{ $review->self_rating ? number_format($review->self_rating,2).'/5.00' : '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Supervisor</td>
                        <td style="padding:9px 0;font-weight:700">{{ $review->supervisor_rating ? number_format($review->supervisor_rating,2).'/5.00' : '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0;color:#6b7280">Final Score</td>
                        <td style="padding:9px 0;font-size:16px;font-weight:800;color:{{ ($review->overall_score ?? 0) >= 4 ? 'var(--hims-primary)' : (($review->overall_score ?? 0) < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                            {{ $review->overall_score ? number_format($review->overall_score,2).'/5.00' : '—' }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        {{-- KPI Scores --}}
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-bar-chart-fill"></i> KPI Scores</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>KPI</th><th>Weight</th><th>Self</th><th>Supervisor</th><th>Weighted</th></tr></thead>
                    <tbody>
                        @forelse($kpi_scores ?? [] as $k)
                        <tr>
                            <td><strong>{{ $k->kpi_name ?? '—' }}</strong></td>
                            <td>{{ $k->weight_pct ?? 0 }}%</td>
                            <td>{{ $k->self_score ?? '—' }}</td>
                            <td>{{ $k->supervisor_score ?? '—' }}</td>
                            <td><strong>{{ $k->weighted_score ? number_format($k->weighted_score,2) : '—' }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">No KPI scores recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Goals --}}
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-flag-fill"></i> Goals</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Goal</th><th>Target</th><th>Achievement</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($goals ?? [] as $g)
                        <tr>
                            <td>{{ $g->goal_description }}</td>
                            <td>{{ $g->target_value ?? '—' }}</td>
                            <td>{{ $g->achievement_value ?? '—' }}</td>
                            <td><span class="hims-badge {{ $g->status === 'achieved' ? 'green' : ($g->status === 'not_achieved' ? 'red' : 'yellow') }}">{{ ucfirst(str_replace('_',' ',$g->status)) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">No goals set.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Peer Reviews --}}
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-people"></i> Peer Feedback</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @forelse($peer_reviews ?? [] as $p)
                <div style="padding:12px 14px;background:var(--hims-primary-pale);border-radius:8px;border:1px solid var(--hims-border)">
                    <div style="font-weight:600;font-size:12.5px;color:#6b7280;margin-bottom:4px">{{ $p->reviewer }} · Rating: <strong style="color:var(--hims-text-dark)">{{ $p->overall_rating ?? '—' }}/5</strong></div>
                    <div style="font-size:13px">{{ $p->feedback_text ?? '—' }}</div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No peer feedback submitted.</div>
                @endforelse
            </div>
        </div>

        {{-- PIP --}}
        @if($pip ?? null)
        <div class="hims-card" style="border:2px solid var(--hims-danger)">
            <div class="card-header" style="background:#fee2e2">
                <h5 style="color:var(--hims-danger)"><i class="bi bi-exclamation-triangle-fill"></i> Performance Improvement Plan (PIP)</h5>
                <span class="hims-badge red">{{ ucfirst($pip->status) }}</span>
            </div>
            <div class="card-body">
                <div style="font-size:13px;margin-bottom:8px"><strong>Start:</strong> {{ $pip->start_date ?? '—' }} &nbsp;|&nbsp; <strong>End:</strong> {{ $pip->end_date ?? '—' }}</div>
                <div style="font-size:13px">{{ $pip->reason ?? '—' }}</div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
