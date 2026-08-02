@extends('layouts.hims')
@section('title','Nominate Candidate')
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Nominate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-person-plus-fill"></i> Nominate Succession Candidate</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('succession.candidates.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">Employee *</label>
                            <select name="employee_id" class="hims-input hims-select" required>
                                <option value="">— Select Employee —</option>
                                @foreach($employees ?? [] as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Target Position *</label>
                            <select name="position_id" class="hims-input hims-select" required>
                                <option value="">— Select Position —</option>
                                @foreach($positions ?? [] as $pos)
                                    <option value="{{ $pos->position_id }}">{{ $pos->position_title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Performance Score (1–5) *</label>
                            <input type="number" name="performance_score" id="perf-score" class="hims-input" value="{{ old('performance_score') }}" min="1" max="5" step="1" required placeholder="e.g. 4">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Potential Score (1–5) *</label>
                            <input type="number" name="potential_score" id="pot-score" class="hims-input" value="{{ old('potential_score') }}" min="1" max="5" step="1" required placeholder="e.g. 5">
                        </div>
                        <div class="col-md-6">
                            {{-- Derived from the two scores, never submitted. Shown so the
                                 rater can see the placement their scores produce. --}}
                            <label class="hims-label">9-Box Placement</label>
                            <div id="ninebox-preview" class="hims-input" style="background:#f9fafb;display:flex;align-items:center;color:#6b7280">
                                Enter both scores
                            </div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:4px">
                                Calculated automatically from performance and potential.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Readiness Level</label>
                            <select name="readiness_level" class="hims-input hims-select">
                                <option value="ready_now">Ready Now</option>
                                <option value="1_2_years" selected>1–2 Years</option>
                                <option value="2_5_years">2–5 Years</option>
                                <option value="long_term">Long Term (5+ yrs)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Mentor</label>
                            <select name="mentor_id" class="hims-input hims-select">
                                <option value="">— None —</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->employee_id }}" @selected(old('mentor_id') === $emp->employee_id)>
                                        {{ $emp->first_name }} {{ $emp->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Nominate Candidate</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Mirrors SuccessionController::nineBoxLabel(). Display only — the server
     recomputes the stored label, so this can never desynchronise the data. --}}
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
