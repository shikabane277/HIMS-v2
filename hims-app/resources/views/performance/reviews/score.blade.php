@extends('layouts.hims')
@section('title','Score Review')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Reviews / Scoring')

@php use App\Support\ReviewStatus; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $review->employee_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $review->position_title ?? '—' }} · {{ $review->cycle_name }} ·
            <span class="hims-badge {{ ReviewStatus::badgeClass($review->status) }}">{{ ReviewStatus::label($review->status) }}</span>
        </p>
    </div>
    <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Review</a>
</div>

@if($errors->any())
    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
@endif
@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

{{-- The screen is reachable only by the one reviewer named on the review, so
     there is no ownership to explain and nothing greyed out. The deadline is
     the one thing that can take the screen away, so that is what is stated. --}}
<div class="hims-alert mb-3" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af">
    <i class="bi bi-person-badge"></i>
    This is your assessment of {{ $review->employee_name }}. Save it as a draft while you work and mark it
    <strong>finished</strong> when you are done — a finished review can still be edited.
    @if($review->cycle_end_date)
        It closes for good after {{ \Illuminate\Support\Carbon::parse($review->cycle_end_date)->format('d M Y') }}, when the cycle ends.
    @endif
</div>

@if($review->is_exception_review)
    <div class="hims-alert mb-3" style="background:#fef3c7;border-color:#fde68a;color:#92400e">
        <i class="bi bi-shield-exclamation"></i>
        <strong>Exception review.</strong> Written outside the reporting line
        ({{ str_replace('_',' ',$review->exception_basis) }}){{ $review->exception_reason ? ' — '.$review->exception_reason : '' }}
    </div>
@endif

<form method="POST" action="{{ route('performance.reviews.score.save', $review->review_id) }}">
    @csrf
    @method('PUT')

    <div class="hims-card mb-3">
        <div class="card-header">
            <h5><i class="bi bi-speedometer2"></i> KPI Scores</h5>
            <span style="font-size:11.5px;color:#9ca3af">
                {{ $rated_count }} of {{ $scores->count() }} rated
            </span>
        </div>
        @if($scores->isNotEmpty() && $rated_count < $scores->count())
        {{-- Said out loud because the form cannot show it: a blank box is not a
             zero, it is an absence, and absences move everyone else's share. --}}
        <div style="padding:10px 16px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:11.5px;color:#92400e">
            @php $unrated = $scores->count() - $rated_count; @endphp
            <i class="bi bi-info-circle"></i>
            {{ $unrated }} {{ $unrated === 1 ? 'KPI is' : 'KPIs are' }} still unrated. An unrated KPI is left out of
            the final score altogether — it does not count as a zero, and the KPIs you have rated share out its
            influence between them.
        </div>
        @endif
        <div class="card-body" style="padding:0">
            <table class="hims-table">
                <thead>
                    <tr>
                        <th style="min-width:210px">KPI</th>
                        <th style="width:130px">Rating</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scores as $score)
                    <tr>
                        <td data-label="KPI">
                            <strong>{{ $score->kpi_name }}</strong>
                            <div style="font-size:11px;color:#9ca3af">
                                {{ ucfirst(str_replace('_',' ',$score->kpi_category)) }}
                                @if($score->target_value) · target {{ $score->target_value }}{{ $score->unit ? ' '.$score->unit : '' }}@endif
                            </div>
                            {{-- The weight rendered as the thing it actually buys. "weight 0.60"
                                 is not a number a reviewer can use; its share of the score is. --}}
                            <div style="font-size:11px;margin-top:3px">
                                @if(isset($weight_shares[$score->score_id]))
                                    <span style="color:var(--hims-primary);font-weight:600">
                                        counts {{ $weight_shares[$score->score_id] }}% of the final score
                                    </span>
                                @else
                                    <span style="color:#9ca3af">not counted until you rate it</span>
                                @endif
                            </div>
                            @if($score->description)
                            <div style="font-size:11px;color:#6b7280;margin-top:3px;max-width:280px">{{ $score->description }}</div>
                            @endif
                        </td>
                        <td data-label="Rating">
                            <input type="number" step="0.01" min="1" max="5" class="hims-input"
                                   name="scores[{{ $score->score_id }}][supervisor_score]"
                                   value="{{ old('scores.'.$score->score_id.'.supervisor_score', $score->supervisor_score) }}" placeholder="1–5">
                        </td>
                        <td data-label="Comments">
                            <input type="text" class="hims-input"
                                   name="scores[{{ $score->score_id }}][comments]"
                                   value="{{ old('scores.'.$score->score_id.'.comments', $score->comments) }}"
                                   placeholder="Evidence / observation" maxlength="1000">
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:32px">
                        No KPIs attached to this review yet — add some below.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($availableKpis->isNotEmpty())
    <div class="hims-card mb-3">
        <div class="card-header"><h5><i class="bi bi-plus-circle"></i> Add More KPIs</h5></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach($availableKpis as $kpi)
                <div class="col-md-6 col-lg-4">
                    <label style="display:flex;gap:9px;align-items:flex-start;padding:10px 12px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px;cursor:pointer;height:100%">
                        <input type="checkbox" name="add_kpi_ids[]" value="{{ $kpi->kpi_id }}" style="margin-top:3px">
                        <span>
                            <span style="font-size:13px;font-weight:600;display:block">{{ $kpi->kpi_name }}</span>
                            <span style="font-size:11.5px;color:#6b7280">{{ ucfirst(str_replace('_',' ',$kpi->kpi_category)) }} · weight {{ (float) $kpi->weight }}</span>
                        </span>
                    </label>
                </div>
                @endforeach
            </div>
            <div style="font-size:11.5px;color:#9ca3af;margin-top:10px">Ticked KPIs are attached when you save, then become scoreable rows above.</div>
        </div>
    </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="hims-card" style="height:100%">
                <div class="card-header"><h5><i class="bi bi-hand-thumbs-up"></i> Strengths</h5></div>
                <div class="card-body">
                    <textarea name="strengths_text" class="hims-input" rows="5" maxlength="2000"
                              placeholder="What this employee does consistently well…">{{ old('strengths_text', $review->strengths_text) }}</textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="hims-card" style="height:100%">
                <div class="card-header"><h5><i class="bi bi-arrow-up-right-circle"></i> Areas for Improvement</h5></div>
                <div class="card-body">
                    <textarea name="improvements_text" class="hims-input" rows="5" maxlength="2000"
                              placeholder="Where development is needed, and why…">{{ old('improvements_text', $review->improvements_text) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="hims-card mt-3">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
            <div style="min-width:250px">
                <label class="hims-label">Review Status *</label>
                <select name="status" class="hims-input hims-select" required>
                    @foreach(ReviewStatus::SETTABLE as $value)
                    <option value="{{ $value }}" @selected(old('status', $review->status) === $value)>{{ ReviewStatus::label($value) }}</option>
                    @endforeach
                </select>
                <div style="font-size:11.5px;color:#9ca3af;margin-top:4px">
                    <strong>Finished</strong> records a digital signature and still lets you edit.
                    The review turns <strong>Completed</strong> — and read-only — on its own once the cycle ends.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                <button type="submit" class="btn-hims btn-hims-primary">
                    <i class="bi bi-save"></i> Save Scores
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
