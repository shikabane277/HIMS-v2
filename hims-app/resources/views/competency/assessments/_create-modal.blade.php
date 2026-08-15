{{--
    New-competency-assessment form, as a modal. Replaces the standalone create
    page; the route, the POST and the validation are unchanged.

    Needs $employees and $competencies from the host controller.

    `gap` is not a field here — the trg_compute_gap_* MySQL triggers derive it
    from current_proficiency against the competency's required level.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="assessmentCreateModal" role="dialog" aria-modal="true" aria-labelledby="assessmentCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="assessmentCreateTitle"><i class="bi bi-clipboard-check"></i> New Competency Assessment</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('competency.assessments.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="assessmentCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'assessmentCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="hims-label" for="ac_employee_id">Employee *</label>
                        <select name="employee_id" id="ac_employee_id" class="hims-input hims-select" required>
                            <option value="">— Select Employee —</option>
                            @foreach($employees ?? [] as $emp)
                                <option value="{{ $emp->employee_id }}" @selected(old('employee_id') === $emp->employee_id)>
                                    {{ $emp->first_name }} {{ $emp->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ac_competency_id">Competency *</label>
                        <select name="competency_id" id="ac_competency_id" class="hims-input hims-select" required>
                            <option value="">— Select Competency —</option>
                            @foreach($competencies ?? [] as $comp)
                                <option value="{{ $comp->competency_id }}" @selected(old('competency_id') === $comp->competency_id)>
                                    {{ $comp->competency_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ac_current_proficiency">Current Proficiency (1–5) *</label>
                        <input type="number" name="current_proficiency" id="ac_current_proficiency" class="hims-input"
                               value="{{ old('current_proficiency') }}" required min="1" max="5" step="1">
                        <div style="font-size:11.5px;color:#9ca3af;margin:5px 0 8px">
                            The gap against the competency's required level is worked out for you.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ac_assessment_method">Assessment Method</label>
                        <select name="assessment_method" id="ac_assessment_method" class="hims-input hims-select">
                            <option value="self_assessment"   @selected(old('assessment_method')==='self_assessment')>Self Assessment</option>
                            <option value="supervisor_rating" @selected(old('assessment_method')==='supervisor_rating')>Supervisor Rating</option>
                            <option value="practical_test"    @selected(old('assessment_method')==='practical_test')>Practical Test</option>
                            <option value="written_exam"      @selected(old('assessment_method')==='written_exam')>Written Exam</option>
                            <option value="observation"       @selected(old('assessment_method')==='observation')>Direct Observation</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="ac_assessed_date">Assessment Date</label>
                        <input type="date" name="assessed_date" id="ac_assessed_date" class="hims-input"
                               value="{{ old('assessed_date', now()->toDateString()) }}">
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="ac_notes">Notes</label>
                        <textarea name="notes" id="ac_notes" class="hims-input" rows="3" placeholder="Observations and recommendations…">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Assessment</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
@if(request('new') === 'assessment' || ($errors->any() && old('_modal') === 'assessmentCreateModal'))
@push('scripts')
<script>window.himsModal.open('assessmentCreateModal');</script>
@endpush
@endif
