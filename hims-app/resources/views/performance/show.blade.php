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
                <table class="hims-kv" style="width:100%;font-size:13px">
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
                        <tr>
                            <td data-label="KPI"><strong>{{ $k->kpi_name ?? '—' }}</strong></td>
                            <td data-label="Rating">{{ $k->supervisor_score ?? '—' }}</td>
                            {{-- The raw weight said nothing on its own; this is the same
                                 figure expressed as the influence it actually carries. --}}
                            <td data-label="Share of final score">
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-flag-fill"></i> Goals</h5>
                @can('manage-performance')
                <button type="button" class="btn-hims btn-hims-primary btn-sm" data-modal-open="addGoalModal">
                    <i class="bi bi-plus-circle"></i> Add Goal
                </button>
                @endcan
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Goal</th><th>Target Date</th><th>Progress</th><th>Status</th>@can('manage-performance')<th>Action</th>@endcan</tr></thead>
                    <tbody>
                        @forelse($goals ?? [] as $g)
                        <tr id="review-goal-{{ $g->goal_id }}" style="scroll-margin-top:84px">
                            <td data-label="Goal">
                                <strong>{{ $g->goal_title ?? $g->goal_description }}</strong>
                                @if(!empty($g->goal_description) && $g->goal_description !== ($g->goal_title ?? ''))
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ $g->goal_description }}</div>
                                @endif
                            </td>
                            <td data-label="Target Date" style="font-size:12.5px;color:#6b7280">{{ $g->target_date ? \Carbon\Carbon::parse($g->target_date)->format('M d, Y') : '—' }}</td>
                            <td data-label="Progress">
                                <div style="min-width:70px">
                                    <div style="font-size:11px;color:#6b7280;margin-bottom:2px">{{ $g->progress_pct ?? 0 }}%</div>
                                    <div class="hims-progress" style="height:5px"><div class="hims-progress-bar" style="width:{{ $g->progress_pct ?? 0 }}%"></div></div>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="hims-badge {{ $g->status === 'achieved' ? 'green' : ($g->status === 'not_achieved' ? 'red' : 'yellow') }}">
                                    {{ ucfirst(str_replace('_',' ',$g->status)) }}
                                </span>
                            </td>
                            @can('manage-performance')
                            <td data-label="Action">
                                <button type="button" class="btn-hims btn-hims-ghost btn-sm"
                                        data-modal-open="editGoalModal"
                                        data-goal-edit
                                        data-action="{{ route('performance.reviews.goals.update', $g->goal_id) }}"
                                        data-title="{{ $g->goal_title ?? $g->goal_description }}"
                                        data-progress="{{ $g->progress_pct ?? 0 }}"
                                        data-status="{{ $g->status }}">
                                    Update
                                </button>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()->can('manage-performance') ? 5 : 4 }}" class="text-center" style="color:#9ca3af;padding:24px">No goals set yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PIP --}}
        @if($pip ?? null)
        <div class="hims-card mb-3" style="border:2px solid var(--hims-danger)">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#fee2e2">
                <h5 style="color:var(--hims-danger);margin:0"><i class="bi bi-exclamation-triangle-fill"></i> Performance Improvement Plan (PIP)</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="hims-badge red">{{ ucfirst($pip->status) }}</span>
                    @can('manage-performance')
                    <button type="button" class="btn-hims btn-hims-outline btn-sm"
                            data-modal-open="updatePipModal"
                            data-pip-edit
                            data-action="{{ route('performance.reviews.pip.update', $pip->pip_id) }}"
                            data-status="{{ $pip->status }}"
                            data-notes="{{ $pip->notes ?? '' }}">
                        Update PIP
                    </button>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                <div style="font-size:13px;margin-bottom:8px">
                    <strong>Start:</strong> {{ $pip->start_date ?? '—' }} &nbsp;|&nbsp;
                    <strong>Target End:</strong> {{ $pip->target_end_date ?? '—' }}
                    @if($pip->actual_end_date)
                    &nbsp;|&nbsp; <strong>Actual End:</strong> {{ $pip->actual_end_date }}
                    @endif
                </div>
                @if($pip->action_steps)
                    @php $steps = is_string($pip->action_steps) ? json_decode($pip->action_steps, true) : $pip->action_steps; @endphp
                    @if(is_array($steps) && count($steps) > 0)
                    <div style="font-size:12.5px;margin-bottom:8px">
                        <strong>Action Steps:</strong>
                        <ul style="margin:4px 0 0 18px;padding:0">
                            @foreach($steps as $step)
                                <li>{{ is_array($step) ? json_encode($step) : $step }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                @endif
                @if($pip->notes)
                <div style="font-size:12.5px;color:#4b5563"><strong>Notes:</strong> {{ $pip->notes }}</div>
                @endif
            </div>
        </div>
        @else
        @can('manage-performance')
        <div class="mb-3">
            <button type="button" class="btn-hims btn-hims-outline btn-sm" style="color:var(--hims-danger);border-color:var(--hims-danger)" data-modal-open="createPipModal">
                <i class="bi bi-exclamation-triangle"></i> Initiate PIP
            </button>
        </div>
        @endcan
        @endif
    </div>
</div>

@can('manage-performance')
@push('modals')
{{-- Add Goal Modal --}}
<div class="hims-modal-backdrop" id="addGoalModal">
    <div class="hims-modal" style="max-width:520px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-flag-fill"></i> Add Review Goal</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('performance.reviews.goals.store', $review->review_id) }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Goal Title *</label>
                    <input type="text" name="goal_title" class="hims-input" required placeholder="e.g. Complete Advanced Medication Administration Training">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Description / Specific Target</label>
                    <textarea name="goal_description" class="hims-input" rows="2" placeholder="Details of the expected outcome..."></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="hims-label">Target Date</label>
                        <input type="date" name="target_date" class="hims-input">
                    </div>
                    <div class="col-6">
                        <label class="hims-label">Initial Status</label>
                        <select name="status" class="hims-input hims-select">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="achieved">Achieved</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Goal</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Goal Modal --}}
<div class="hims-modal-backdrop" id="editGoalModal">
    <div class="hims-modal" style="max-width:440px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Update Goal Progress</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="editGoalForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Goal</label>
                    <input type="text" id="eg_title" class="hims-input" readonly disabled style="background:#f1f5f9">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Progress Percentage (0–100%)</label>
                    <input type="number" name="progress_pct" id="eg_progress" class="hims-input" min="0" max="100">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Status</label>
                    <select name="status" id="eg_status" class="hims-input hims-select" required>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="achieved">Achieved</option>
                        <option value="not_achieved">Not Achieved</option>
                    </select>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Update</button>
            </div>
        </form>
    </div>
</div>

{{-- Initiate PIP Modal --}}
<div class="hims-modal-backdrop" id="createPipModal">
    <div class="hims-modal" style="max-width:540px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-exclamation-triangle-fill text-danger"></i> Initiate Performance Improvement Plan</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('performance.reviews.pip.store', $review->review_id) }}">
            @csrf
            <div class="hims-modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="hims-label">Start Date *</label>
                        <input type="date" name="start_date" class="hims-input" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-6">
                        <label class="hims-label">Target End Date *</label>
                        <input type="date" name="target_end_date" class="hims-input" required value="{{ date('Y-m-d', strtotime('+90 days')) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Action Steps (one per line) *</label>
                    <textarea name="action_steps" class="hims-input" rows="4" required placeholder="Weekly 1-on-1 check-ins&#10;Complete clinical pharmacology review course&#10;Re-audit medication delivery at 60 days"></textarea>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Supervisor Notes</label>
                    <textarea name="notes" class="hims-input" rows="2" placeholder="Specific performance concerns identified during review..."></textarea>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary" style="background:var(--hims-danger);border-color:var(--hims-danger)"><i class="bi bi-check-circle"></i> Initiate PIP</button>
            </div>
        </form>
    </div>
</div>

{{-- Update PIP Modal --}}
<div class="hims-modal-backdrop" id="updatePipModal">
    <div class="hims-modal" style="max-width:480px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Update PIP Status</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="updatePipForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Status *</label>
                    <select name="status" id="up_status" class="hims-input hims-select" required>
                        <option value="initiated">Initiated</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed Successfully</option>
                        <option value="extended">Extended</option>
                        <option value="failed">Failed / Escalated</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Actual End Date</label>
                    <input type="date" name="actual_end_date" id="up_end_date" class="hims-input">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Notes</label>
                    <textarea name="notes" id="up_notes" class="hims-input" rows="3"></textarea>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save PIP Update</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const goalBtn = e.target.closest('[data-goal-edit]');
    if (goalBtn) {
        const form = document.getElementById('editGoalForm');
        form.action = goalBtn.dataset.action;
        document.getElementById('eg_title').value = goalBtn.dataset.title;
        document.getElementById('eg_progress').value = goalBtn.dataset.progress;
        document.getElementById('eg_status').value = goalBtn.dataset.status;
        return;
    }
    const pipBtn = e.target.closest('[data-pip-edit]');
    if (pipBtn) {
        const form = document.getElementById('updatePipForm');
        form.action = pipBtn.dataset.action;
        document.getElementById('up_status').value = pipBtn.dataset.status;
        document.getElementById('up_notes').value = pipBtn.dataset.notes || '';
    }
});
</script>
@endpush
@endcan

@endsection
