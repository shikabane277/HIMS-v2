@extends('layouts.hims')
@section('title','Review Detail')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Review')

@php use App\Support\ReviewStatus; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Reviews</a>
    <div class="d-flex align-items-center gap-2" style="flex-wrap:wrap">
        @if($review->is_exception_review)
            <span class="hims-badge yellow" style="font-size:12px;padding:6px 12px" title="{{ $review->exception_reason }}">
                <i class="bi bi-shield-exclamation"></i> Exception · {{ str_replace('_',' ',$review->exception_basis) }}
            </span>
        @endif
        <span class="hims-badge {{ ReviewStatus::badgeClass($effective_status) }}" style="font-size:13px;padding:6px 14px">{{ ReviewStatus::label($effective_status) }}</span>
        {{-- Only the reviewer named on the review gets the button, and only while
             the cycle is still open. A role alone puts nothing here. --}}
        @if($can_score)
            <a href="{{ route('performance.reviews.score', $review->review_id) }}" class="btn-hims btn-hims-primary btn-sm">
                <i class="bi bi-pencil-square"></i> Score Review
            </a>
        @endif
    </div>
</div>

@if($effective_status === ReviewStatus::COMPLETED)
    <div class="hims-alert mb-3" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46">
        <i class="bi bi-lock-fill"></i>
        <strong>Completed.</strong> The cycle ended on {{ \Illuminate\Support\Carbon::parse($review->cycle_end_date)->format('d M Y') }}, so this review is final and can no longer be edited.
    </div>
@endif

@if($review->is_exception_review)
    <div class="hims-alert mb-3" style="background:#fef3c7;border-color:#fde68a;color:#92400e">
        <i class="bi bi-shield-exclamation"></i>
        <strong>Written outside the reporting line.</strong>
        Basis: {{ str_replace('_',' ',$review->exception_basis) }}{{ $review->exception_reason ? ' — '.$review->exception_reason : '' }}
    </div>
@endif

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
                        <td style="padding:9px 0;color:#6b7280">Reviewer</td>
                        <td style="padding:9px 0;font-weight:600">{{ trim($review->reviewer_name ?? '') ?: '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Review status</td>
                        <td style="padding:9px 0;font-weight:600">{{ ReviewStatus::label($effective_status) }}</td>
                    </tr>
                    @if($review->signed_at)
                    <tr>
                        <td style="padding:9px 0;color:#6b7280">Signed</td>
                        <td style="padding:9px 0">{{ \Illuminate\Support\Carbon::parse($review->signed_at)->format('d M Y H:i') }}</td>
                    </tr>
                    @endif
                    {{-- Two numbers that differ for a reason nothing on screen used
                         to give. "Supervisor Rating" read as "the rating the
                         supervisor gave" — which is both of them — so the labels now
                         say what each one is an average of. --}}
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Average of KPI ratings</td>
                        <td style="padding:9px 0;font-weight:700;color:var(--hims-primary)">{{ $review->supervisor_rating ? number_format($review->supervisor_rating,2).'/5.00' : '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0;color:#6b7280">
                            Final Score
                            <div style="font-size:11px;color:#9ca3af;font-weight:400">weighted by KPI importance</div>
                        </td>
                        <td style="padding:9px 0;font-size:16px;font-weight:800;color:{{ ($review->overall_score ?? 0) >= 4 ? 'var(--hims-primary)' : (($review->overall_score ?? 0) < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                            {{ $review->overall_score ? number_format($review->overall_score,2).'/5.00' : '—' }}
                        </td>
                    </tr>
                </table>
                @if($review->supervisor_rating || $review->overall_score)
                <div style="font-size:11px;color:#6b7280;margin-top:10px;line-height:1.5">
                    The two differ because KPIs do not count equally: the average treats every KPI alike,
                    while the Final Score gives each one the share shown in the table. The Final Score is the
                    figure the rest of HIMS quotes.
                </div>
                @endif
            </div>
        </div>

        @if($is_subject && $effective_status !== ReviewStatus::DRAFT)
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-chat-left-text"></i> Employee Response</h5></div>
            <div class="card-body">
                @if($review->employee_acknowledged_at)
                    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> Acknowledged on {{ \Illuminate\Support\Carbon::parse($review->employee_acknowledged_at)->format('d M Y H:i') }}.</div>
                    <p style="white-space:pre-line;margin:0;color:#374151">{{ $review->employee_response ?: 'No response provided.' }}</p>
                @elseif($can_respond)
                    <form method="POST" action="{{ route('performance.reviews.employee-response.store', $review->review_id) }}">
                        @csrf
                        <label class="hims-label" for="employee_response">Response or appeal</label>
                        <textarea id="employee_response" name="employee_response" class="hims-input" rows="5" maxlength="4000" placeholder="Add your response to the completed review">{{ old('employee_response', $review->employee_response) }}</textarea>
                        @if($effective_status === ReviewStatus::COMPLETED)
                        <label class="d-flex align-items-start gap-2 mt-3" style="font-size:13px">
                            <input type="checkbox" name="acknowledge" value="1" required style="margin-top:3px">
                            <span>I acknowledge that I have reviewed these scores and comments.</span>
                        </label>
                        @endif
                        <button type="submit" class="btn-hims btn-hims-primary btn-sm mt-3"><i class="bi bi-send"></i> {{ $effective_status === ReviewStatus::COMPLETED ? 'Save and acknowledge' : 'Save response' }}</button>
                    </form>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-8">
        {{-- KPI Scores --}}
        <div class="hims-card mb-3">
            <div class="card-header">
                <h5><i class="bi bi-bar-chart-fill"></i> KPI Scores</h5>
                <span style="font-size:11.5px;color:#9ca3af">
                    {{ $rated_count ?? 0 }} of {{ count($kpi_scores ?? []) }} rated
                </span>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>KPI</th><th>Rating</th><th>Share of final score</th></tr></thead>
                    <tbody>
                        @forelse($kpi_scores ?? [] as $k)
                        <tr id="review-goal-{{ $g->goal_id }}" style="scroll-margin-top:84px">
                            <td><strong>{{ $k->kpi_name ?? '—' }}</strong></td>
                            <td>{{ $k->supervisor_score ?? '—' }}</td>
                            {{-- The raw weight said nothing on its own; this is the same
                                 figure expressed as the influence it actually carries. --}}
                            <td>
                                @if(isset($weight_shares[$k->score_id]))
                                    <strong>{{ $weight_shares[$k->score_id] }}%</strong>
                                @else
                                    <span style="color:#9ca3af">not rated, not counted</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">No KPI scores recorded.</td></tr>
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
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:24px">No goals set.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
