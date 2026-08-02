@extends('layouts.hims')
@section('title','Score Review')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Reviews / Scoring')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $review->employee_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $review->position_title ?? '—' }} · {{ $review->cycle_name }} ·
            <span class="hims-badge {{ $review->status === 'completed' ? 'green' : 'yellow' }}">{{ ucfirst(str_replace('_',' ',$review->status)) }}</span>
        </p>
    </div>
    <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Review</a>
</div>

@if($errors->any())
    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
@endif
@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('performance.reviews.score.save', $review->review_id) }}">
    @csrf
    @method('PUT')

    <div class="hims-card mb-3">
        <div class="card-header">
            <h5><i class="bi bi-speedometer2"></i> KPI Scores</h5>
            <span style="font-size:11.5px;color:#9ca3af">
                Weighted as supervisor 50% · self 30% · peer 20% (missing inputs are excluded and the rest re-normalised)
            </span>
        </div>
        <div class="card-body" style="padding:0">
            <table class="hims-table">
                <thead>
                    <tr>
                        <th style="min-width:210px">KPI</th>
                        <th style="width:110px">Self</th>
                        <th style="width:110px">Supervisor</th>
                        <th style="width:110px">Peer</th>
                        <th style="width:100px">Weighted</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scores as $score)
                    <tr>
                        <td>
                            <strong>{{ $score->kpi_name }}</strong>
                            <div style="font-size:11px;color:#9ca3af">
                                {{ ucfirst(str_replace('_',' ',$score->kpi_category)) }} · weight {{ (float) $score->weight }}
                                @if($score->target_value) · target {{ $score->target_value }}{{ $score->unit ? ' '.$score->unit : '' }}@endif
                            </div>
                            @if($score->description)
                            <div style="font-size:11px;color:#6b7280;margin-top:3px;max-width:280px">{{ $score->description }}</div>
                            @endif
                        </td>
                        <td>
                            <input type="number" step="0.01" min="1" max="5" class="hims-input"
                                   name="scores[{{ $score->score_id }}][self_score]"
                                   value="{{ old('scores.'.$score->score_id.'.self_score', $score->self_score) }}" placeholder="1–5">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="1" max="5" class="hims-input"
                                   name="scores[{{ $score->score_id }}][supervisor_score]"
                                   value="{{ old('scores.'.$score->score_id.'.supervisor_score', $score->supervisor_score) }}" placeholder="1–5">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="1" max="5" class="hims-input"
                                   name="scores[{{ $score->score_id }}][peer_score]"
                                   value="{{ old('scores.'.$score->score_id.'.peer_score', $score->peer_score) }}" placeholder="1–5">
                        </td>
                        <td>
                            @if($score->weighted_score !== null)
                                <span class="gap-chip {{ (float) $score->weighted_score >= 3.5 ? 'positive' : 'negative' }}">
                                    {{ number_format((float) $score->weighted_score, 2) }}
                                </span>
                            @else
                                <span style="color:#9ca3af">—</span>
                            @endif
                        </td>
                        <td>
                            <input type="text" class="hims-input"
                                   name="scores[{{ $score->score_id }}][comments]"
                                   value="{{ old('scores.'.$score->score_id.'.comments', $score->comments) }}"
                                   placeholder="Evidence / observation" maxlength="1000">
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
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
                    @foreach(['draft'=>'Draft','self_assessment'=>'Self Assessment','supervisor_review'=>'Supervisor Review','calibration'=>'Calibration','completed'=>'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $review->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div style="font-size:11.5px;color:#9ca3af;margin-top:4px">
                    Setting <strong>Completed</strong> locks in the overall score and records a digital signature.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-save"></i> Save Scores</button>
            </div>
        </div>
    </div>
</form>
@endsection
