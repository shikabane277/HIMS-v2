@extends('layouts.hims')
@section('title','Edit Candidate')
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Edit Candidate')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Edit Nomination</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $candidate->employee_name }}
            <i class="bi bi-arrow-right" style="margin:0 4px"></i>
            <strong style="color:var(--hims-text-dark)">{{ $candidate->target_title }}</strong>
        </p>
    </div>
    <a href="{{ route('succession.candidates.show', $candidate->candidate_id) }}" class="btn-hims btn-hims-ghost">
        <i class="bi bi-arrow-left"></i> Back to Candidate
    </a>
</div>

@if($errors->any())
    <div class="hims-alert error mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul style="margin:4px 0 0 18px;padding:0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-pencil-square"></i> Assessment</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('succession.candidates.update', $candidate->candidate_id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">Performance Score (1–5) *</label>
                            <input type="number" name="performance_score" id="perf-score" class="hims-input"
                                   value="{{ old('performance_score', $candidate->performance_score) }}"
                                   min="1" max="5" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Potential Score (1–5) *</label>
                            <input type="number" name="potential_score" id="pot-score" class="hims-input"
                                   value="{{ old('potential_score', $candidate->potential_score) }}"
                                   min="1" max="5" step="1" required>
                        </div>
                        <div class="col-md-6">
                            {{-- Derived server-side on save; shown here as a preview only. --}}
                            <label class="hims-label">9-Box Placement</label>
                            <div id="ninebox-preview" class="hims-input" style="background:#f9fafb;display:flex;align-items:center">
                                —
                            </div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:4px">
                                Recalculated automatically when you save.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Readiness Level *</label>
                            <select name="readiness_level" class="hims-input hims-select" required>
                                @foreach(['ready_now'=>'Ready Now','1_2_years'=>'1–2 Years','2_5_years'=>'2–5 Years','long_term'=>'Long Term (5+ yrs)'] as $val => $text)
                                    <option value="{{ $val }}" @selected(old('readiness_level', $candidate->readiness_level) === $val)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Mentor</label>
                            <select name="mentor_id" class="hims-input hims-select">
                                <option value="">— None —</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->employee_id }}" @selected(old('mentor_id', $candidate->mentor_id) === $emp->employee_id)>
                                        {{ $emp->first_name }} {{ $emp->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('succession.candidates.show', $candidate->candidate_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-exclamation-triangle"></i> Withdraw</h5></div>
            <div class="card-body">
                <p style="font-size:13px;color:#6b7280;margin:0 0 12px">
                    Removes this nomination and its development milestones from the pipeline.
                    The employee can be nominated again later.
                </p>
                <form method="POST" action="{{ route('succession.candidates.withdraw', $candidate->candidate_id) }}"
                      onsubmit="return confirm('Withdraw {{ addslashes($candidate->employee_name) }} from the pipeline? Their development milestones will also be removed.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-hims btn-hims-danger" style="width:100%">
                        <i class="bi bi-person-dash"></i> Withdraw Candidate
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Mirrors SuccessionController::nineBoxLabel() for preview only. --}}
<script>
(function () {
    var LABELS = {
        high_high: '⭐ Future Star',   high_med: '🔝 High Performer', high_low: '✅ Solid Contributor',
        med_high:  '🌱 High Potential', med_med:  '👷 Core Employee',   med_low:  '📊 Average',
        low_high:  '💎 Rough Diamond',  low_med:  '⚠️ Inconsistent',    low_low:  '🔴 Underperformer'
    };
    var perf = document.getElementById('perf-score'),
        pot  = document.getElementById('pot-score'),
        out  = document.getElementById('ninebox-preview');

    function band(v) { return v >= 4 ? 'high' : (v === 3 ? 'med' : 'low'); }

    function update() {
        var p = parseInt(perf.value, 10), q = parseInt(pot.value, 10);
        if (!p || !q || p < 1 || p > 5 || q < 1 || q > 5) {
            out.textContent = 'Enter both scores';
            out.style.color = '#6b7280';
            return;
        }
        out.textContent = LABELS[band(p) + '_' + band(q)];
        out.style.color = 'var(--hims-text-dark)';
    }

    perf.addEventListener('input', update);
    pot.addEventListener('input', update);
    update();
})();
</script>
@endsection
