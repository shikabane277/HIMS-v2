@extends('layouts.hims')
@section('title','Gap Analysis — '.$analysis['employee']->first_name.' '.$analysis['employee']->last_name)
@section('page-title','AI-Assisted Competency Gap Analysis')
@section('breadcrumb','HIMS / Competency / Gap Analysis / Employee')

@section('content')
@php
    $e   = $analysis['employee'];
    $s   = $analysis['summary'];
    $ai  = $analysis['ai'];
    $gaps = collect($analysis['gaps']);
    $openGaps = $gaps->whereIn('status', ['below','unassessed']);
@endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $e->first_name }} {{ $e->last_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $e->position_title ?? $e->role_name }} · {{ $e->department_name }} · {{ $e->employee_code }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
        @can('view-employees')
        <a href="{{ route('employees.show', $e->employee_id) }}" class="btn-hims btn-hims-outline"><i class="bi bi-person"></i> Profile</a>
        @endcan
    </div>
</div>

{{-- Evidence summary --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value">{{ $s['readiness_pct'] }}%</div>
            <div class="stat-label">Requirement Readiness</div>
            <div class="stat-change up"><i class="bi bi-check2-circle"></i> {{ $s['met'] }} of {{ $s['competencies_required'] }} met</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">🔴</div>
            <div class="stat-value">{{ $s['critical_gaps'] }}</div>
            <div class="stat-label">Critical Gaps</div>
            <div class="stat-change down"><i class="bi bi-exclamation-triangle"></i> {{ $s['moderate_gaps'] }} moderate</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value">{{ $s['latest_overall_score'] ? number_format((float) $s['latest_overall_score'],2) : '—' }}</div>
            <div class="stat-label">Latest Review Score</div>
            <div class="stat-change up"><i class="bi bi-bar-chart"></i> {{ $analysis['performance']['latest_cycle'] ?? 'No completed review' }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">🎓</div>
            <div class="stat-value">{{ $s['cpd_hours_last_year'] }}</div>
            <div class="stat-label">CPD Hours (12 mo)</div>
            <div class="stat-change up"><i class="bi bi-mortarboard"></i> {{ $s['courses_completed'] }} courses, {{ $s['trainings_attended'] }} sessions</div>
        </div>
    </div>
</div>

{{-- ── AI findings ── --}}
<div class="hims-card mb-4" style="border-left:4px solid var(--hims-primary)">
    <div class="card-header">
        <h5><i class="bi bi-robot"></i> AI Analysis</h5>
        <span style="font-size:11.5px;color:#9ca3af">Generated {{ $analysis['generated_at']->diffForHumans() }}</span>
    </div>
    <div class="card-body">
        @if(! $ai)
            <div style="color:#9ca3af;font-size:13px">AI narrative was not requested for this run.</div>
        @elseif(! empty($ai['unavailable']))
            <div class="hims-alert error" style="margin:0">
                <i class="bi bi-exclamation-circle-fill"></i>
                {{ $ai['message'] ?? 'AI analysis unavailable.' }}
                <div style="font-size:12px;margin-top:6px;color:#6b7280">
                    The measured findings below are computed directly from the database and remain accurate without the AI layer.
                </div>
            </div>
        @else
            @if(!empty($ai['headline']))
            <p style="font-size:14.5px;font-weight:600;line-height:1.6;margin-bottom:16px">{{ $ai['headline'] }}</p>
            @endif

            @php
                // The model is asked for an object, but a flattened string is a
                // plausible thing for it to return and is still worth showing.
                $fb = $ai['feedback_summary'] ?? null;
                $fb = is_string($fb) && trim($fb) !== '' ? ['overview' => $fb] : (is_array($fb) ? $fb : null);
            @endphp
            @if($fb)
            <div class="mb-3" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px">
                <div class="hims-label" style="margin-bottom:8px">
                    <i class="bi bi-chat-quote"></i> Summary of Supervisor Feedback
                </div>

                @if(!empty($fb['overview']))
                <p style="font-size:13px;color:#374151;line-height:1.65;margin:0 0 11px">{{ $fb['overview'] }}</p>
                @endif

                @if(!empty($fb['recurring_themes']))
                <div class="d-flex flex-column gap-2 mb-2">
                    @foreach((array) $fb['recurring_themes'] as $theme)
                    @php
                        $theme = is_array($theme) ? $theme : ['theme' => (string) $theme];
                        $dir = strtolower((string) ($theme['direction'] ?? ''));
                        $dirBadge = match($dir) {
                            'improving', 'resolved' => 'green',
                            'persistent' => 'red',
                            'new' => 'yellow',
                            default => 'gray',
                        };
                    @endphp
                    <div style="padding:9px 11px;background:#fff;border:1px solid #e5e7eb;border-radius:8px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <strong style="font-size:12.5px">{{ $theme['theme'] ?? '—' }}</strong>
                            <span class="hims-badge {{ $dirBadge }}">{{ ucfirst($dir ?: 'noted') }}</span>
                        </div>
                        @if(!empty($theme['detail']))
                        <div style="font-size:12px;color:#4b5563;margin-top:4px;line-height:1.55">{{ $theme['detail'] }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                <div class="row g-3">
                    @foreach(['praised' => 'Praised For', 'concerns' => 'Concerns Raised'] as $fbKey => $fbLabel)
                    @if(!empty($fb[$fbKey]))
                    <div class="col-md-6">
                        <div class="hims-label" style="margin-bottom:6px">{{ $fbLabel }}</div>
                        <ul style="font-size:12.5px;color:#4b5563;padding-left:18px;margin:0;line-height:1.8">
                            @foreach((array) $fb[$fbKey] as $item)<li>{{ is_array($item) ? reset($item) : $item }}</li>@endforeach
                        </ul>
                    </div>
                    @endif
                    @endforeach
                </div>

                <div style="font-size:11px;color:#9ca3af;margin-top:10px">
                    Summarised from the {{ $s['written_feedback'] }} written comment{{ $s['written_feedback'] === 1 ? '' : 's' }}
                    reproduced in full under “Written Feedback on Record” below.
                </div>
            </div>
            @endif

            @if(!empty($ai['missing_skills']))
            <div class="mb-3">
                <div class="hims-label" style="margin-bottom:8px">Missing Skills Identified</div>
                <div class="d-flex flex-column gap-2">
                    @foreach($ai['missing_skills'] as $skill)
                    @php $sev = strtolower($skill['severity'] ?? 'moderate'); @endphp
                    <div style="padding:11px 13px;background:{{ $sev === 'critical' ? '#fee2e2' : ($sev === 'low' ? '#f0fdf4' : '#fef3c7') }};border-radius:8px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <strong style="font-size:13px">{{ $skill['skill'] ?? '—' }}</strong>
                            <span class="hims-badge {{ $sev === 'critical' ? 'red' : ($sev === 'low' ? 'green' : 'yellow') }}">{{ ucfirst($sev) }}</span>
                        </div>
                        @if(!empty($skill['evidence']))
                        <div style="font-size:12px;color:#4b5563;margin-top:4px"><strong>Evidence:</strong> {{ $skill['evidence'] }}</div>
                        @endif
                        @if(!empty($skill['impact']))
                        <div style="font-size:12px;color:#4b5563;margin-top:2px"><strong>Impact:</strong> {{ $skill['impact'] }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($ai['development_plan']))
            <div class="mb-3">
                <div class="hims-label" style="margin-bottom:8px">Suggested Development Plan</div>
                <table class="hims-table">
                    <thead><tr><th>Step</th><th>Method</th><th>Timeframe</th><th>Success Measure</th></tr></thead>
                    <tbody>
                        @foreach($ai['development_plan'] as $step)
                        <tr>
                            <td><strong>{{ $step['step'] ?? '—' }}</strong></td>
                            <td><span class="hims-badge blue">{{ ucfirst(str_replace('_',' ', $step['method'] ?? '—')) }}</span></td>
                            <td style="font-size:12px">{{ $step['timeframe'] ?? '—' }}</td>
                            <td style="font-size:12px;color:#6b7280">{{ $step['success_measure'] ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="row g-3">
                @if(!empty($ai['root_causes']))
                <div class="col-md-6">
                    <div class="hims-label" style="margin-bottom:6px">Likely Root Causes</div>
                    <ul style="font-size:12.5px;color:#4b5563;padding-left:18px;margin:0;line-height:1.8">
                        @foreach((array) $ai['root_causes'] as $cause)<li>{{ $cause }}</li>@endforeach
                    </ul>
                </div>
                @endif
                @if(!empty($ai['strengths_to_leverage']))
                <div class="col-md-6">
                    <div class="hims-label" style="margin-bottom:6px">Strengths to Leverage</div>
                    <ul style="font-size:12.5px;color:#4b5563;padding-left:18px;margin:0;line-height:1.8">
                        @foreach((array) $ai['strengths_to_leverage'] as $strength)<li>{{ $strength }}</li>@endforeach
                    </ul>
                </div>
                @endif
            </div>

            @if(!empty($ai['training_already_tried']))
            <div class="hims-alert" style="margin:16px 0 0;background:#fef3c7;border-color:#fde68a;color:#92400e">
                <i class="bi bi-arrow-repeat"></i> {{ $ai['training_already_tried'] }}
            </div>
            @endif
        @endif
    </div>
</div>

{{-- ── Measured gaps (deterministic) ── --}}
<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-clipboard-data"></i> Measured Position vs Job Requirements</h5>
        <span class="hims-badge {{ $openGaps->isEmpty() ? 'green' : 'yellow' }}">
            {{ $openGaps->count() }} open item{{ $openGaps->count() === 1 ? '' : 's' }}
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Competency</th><th>Domain</th><th>Required</th><th>Current</th><th>Gap</th><th>Status</th><th>Notes</th></tr>
            </thead>
            <tbody>
                @forelse($gaps as $gap)
                <tr>
                    <td>
                        <strong>{{ $gap['competency_name'] }}</strong>
                        <div style="font-size:11px;color:#9ca3af">{{ $gap['competency_code'] }}</div>
                        @if($gap['is_mandatory'])<span class="hims-badge red">Mandatory</span>@endif
                        @if($gap['is_critical'])<span class="hims-badge yellow">Critical to role</span>@endif
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ $gap['domain'] ?? '—' }}</td>
                    <td>{{ $gap['required'] }}/5</td>
                    <td>{{ $gap['current'] !== null ? $gap['current'].'/5' : '—' }}</td>
                    <td>
                        @if($gap['gap'] === null)
                            <span class="hims-badge gray">n/a</span>
                        @else
                            <span class="gap-chip {{ $gap['gap'] >= 0 ? 'positive' : 'negative' }}">{{ $gap['gap'] >= 0 ? '+' : '' }}{{ $gap['gap'] }}</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $badge = match($gap['status']) {
                                'met' => 'green',
                                'unassessed' => 'gray',
                                default => $gap['severity'] === 'critical' ? 'red' : 'yellow',
                            };
                        @endphp
                        <span class="hims-badge {{ $badge }}">
                            {{ $gap['status'] === 'unassessed' ? 'Not assessed' : ($gap['status'] === 'met' ? 'Met' : ucfirst($gap['severity']).' gap') }}
                        </span>
                    </td>
                    <td style="font-size:11.5px;color:#6b7280;max-width:260px">{{ $gap['note'] }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:32px">
                    No competency requirements are defined for this employee's job role, so there is nothing to measure against.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Written feedback on record (deterministic — this is what the AI summarised) ── --}}
<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-chat-left-text"></i> Written Feedback on Record</h5>
        <span class="hims-badge {{ $s['written_feedback'] ? 'blue' : 'gray' }}">
            {{ $s['written_feedback'] }} comment{{ $s['written_feedback'] === 1 ? '' : 's' }}
        </span>
    </div>
    <div class="card-body d-flex flex-column gap-3">
        @forelse($analysis['performance']['feedback'] as $cycle)
        <div style="border-left:3px solid var(--hims-primary);padding-left:13px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <strong style="font-size:13.5px">
                    {{ $cycle['cycle_name'] }}
                    @if($cycle['is_draft'])<span class="hims-badge gray">Draft</span>@endif
                </strong>
                <span style="font-size:11.5px;color:#9ca3af">
                    @if($cycle['end_date'])Cycle ended {{ $cycle['end_date'] }} · @endif
                    Rating {{ \App\Support\ReviewFeedback::formatScore($cycle['supervisor_rating']) }}
                </span>
            </div>

            @if($cycle['comment_count'] === 0)
            <div style="font-size:12px;color:#9ca3af;margin-top:6px">
                No written comment was recorded in this cycle — only scores.
            </div>
            @else
                @if($cycle['strengths'])
                <div style="font-size:12.5px;color:#374151;line-height:1.6;margin-top:8px;padding:8px 11px;background:#f0fdf4;border-radius:8px">
                    <strong style="font-size:11.5px;color:#166534;text-transform:uppercase;letter-spacing:.03em">Strengths</strong>
                    <div style="margin-top:3px">{{ $cycle['strengths'] }}</div>
                </div>
                @endif

                @if($cycle['improvements'])
                <div style="font-size:12.5px;color:#374151;line-height:1.6;margin-top:6px;padding:8px 11px;background:#fef3c7;border-radius:8px">
                    <strong style="font-size:11.5px;color:#92400e;text-transform:uppercase;letter-spacing:.03em">Areas to Improve</strong>
                    <div style="margin-top:3px">{{ $cycle['improvements'] }}</div>
                </div>
                @endif

                @foreach($cycle['kpi_comments'] as $comment)
                <div style="font-size:12.5px;color:#374151;line-height:1.6;margin-top:6px">
                    <span class="hims-badge blue">{{ $comment['kpi_name'] }}</span>
                    <span style="font-size:11.5px;color:#6b7280">
                        {{ $comment['kpi_category'] ? ucfirst($comment['kpi_category']).' · ' : '' }}
                        {{ $comment['score'] !== null ? 'rated '.\App\Support\ReviewFeedback::formatScore($comment['score']) : 'not scored' }}
                    </span>
                    <div style="margin-top:2px">{{ $comment['comment'] }}</div>
                </div>
                @endforeach
            @endif
        </div>
        @empty
        <div style="text-align:center;color:#9ca3af;padding:24px;font-size:13px">
            No performance reviews on record for this employee yet, so there is no written feedback to summarise.
        </div>
        @endforelse
    </div>
</div>

<div class="row g-3">
    {{-- Recommended interventions from the hospital's own catalogue --}}
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-lightbulb"></i> Recommended Training (from your catalogue)</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Item</th><th>Type</th><th>CPD</th><th>Why</th></tr></thead>
                    <tbody>
                        @forelse($analysis['recommendations'] as $rec)
                        <tr>
                            <td>
                                @if($rec['type'] === 'course')
                                    <a href="{{ route('learning.courses.show', $rec['id']) }}" style="font-weight:700;color:var(--hims-primary)">{{ $rec['title'] }}</a>
                                @else
                                    <a href="{{ route('training.sessions.show', $rec['id']) }}" style="font-weight:700;color:var(--hims-primary)">{{ $rec['title'] }}</a>
                                @endif
                                <div style="font-size:11px;color:#9ca3af">{{ $rec['detail'] }}</div>
                                @if(!empty($rec['addresses_gaps']))
                                <div style="margin-top:5px;display:flex;gap:4px;flex-wrap:wrap">
                                    @foreach($rec['addresses_gaps'] as $gapName)
                                    <span class="hims-badge yellow">{{ $gapName }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                            <td><span class="hims-badge blue">{{ $rec['type'] === 'course' ? 'Course' : 'Session' }}</span></td>
                            <td>{{ $rec['cpd_hours'] ?? '—' }}</td>
                            <td style="font-size:11.5px;color:#6b7280">{{ $rec['reason'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:28px">
                            No matching courses or sessions found. Consider adding training mapped to the open competencies.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- Weak KPIs feeding the analysis --}}
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-speedometer2"></i> Weakest KPIs</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>KPI</th><th>Category</th><th>Score</th></tr></thead>
                    <tbody>
                        @forelse($analysis['performance']['weak_kpis'] as $kpi)
                        <tr>
                            <td><strong>{{ $kpi->kpi_name }}</strong></td>
                            <td style="font-size:12px;color:#6b7280">{{ ucfirst($kpi->kpi_category) }}</td>
                            <td><span class="gap-chip negative">{{ number_format((float) $kpi->weighted_score, 2) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:22px">No weak KPIs recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Credentials at risk --}}
        <div class="hims-card">
            <div class="card-header"><h5>🪪 Credentials Expiring (90 days)</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @forelse($analysis['credentials'] as $cred)
                @php $expired = $cred->expiry_date < now()->toDateString(); @endphp
                <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 11px;background:{{ $expired ? '#fee2e2' : '#fef3c7' }};border-radius:8px">
                    <div>
                        <div style="font-size:12.5px;font-weight:600">{{ $cred->credential_type }}</div>
                        <div style="font-size:11px;color:#6b7280">Expires {{ $cred->expiry_date }}</div>
                    </div>
                    <span class="hims-badge {{ $expired ? 'red' : 'yellow' }}">{{ $expired ? 'Expired' : 'Soon' }}</span>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No credentials expiring soon ✅</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
