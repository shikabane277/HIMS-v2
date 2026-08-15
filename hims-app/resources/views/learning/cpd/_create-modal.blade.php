{{--
    CPD entry, as a modal. Replaces learning/cpd/create.blade.php; the route, the
    POST and the validation are unchanged.

    Needs $canLogForOthers, $employees, $courses and $sessions from the host
    controller — cpdIndex() loads them now that createCpd() is gone.

    Reached from two places: the button on this page, and a ?new=cpd deep-link
    used by learning/my-cycles, which cannot open a modal that lives here.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="cpdCreateModal" role="dialog" aria-modal="true" aria-labelledby="cpdCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="cpdCreateTitle"><i class="bi bi-award"></i> Record CPD Hours</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('learning.cpd.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="cpdCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'cpdCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    @if($canLogForOthers)
                    <div class="col-12">
                        <label class="hims-label" for="cpd_employee_id">Employee</label>
                        <select name="employee_id" id="cpd_employee_id" class="hims-input hims-select">
                            <option value="">Myself</option>
                            @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}" @selected(old('employee_id') === $employee->employee_id)>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                            @endforeach
                        </select>
                        <small style="color:#9ca3af;font-size:11.5px">Leave as “Myself” to log your own hours.</small>
                    </div>
                    @endif

                    <div class="col-12">
                        <label class="hims-label" for="cpd_source_type">Source *</label>
                        <select name="source_type" id="cpd_source_type" class="hims-input hims-select" required>
                            <option value="external" @selected(old('source_type','external') === 'external')>External activity — needs HR verification</option>
                            <option value="course"   @selected(old('source_type') === 'course')>HIMS course</option>
                            <option value="training" @selected(old('source_type') === 'training')>HIMS training session</option>
                        </select>
                        <small style="color:#9ca3af;font-size:11.5px">
                            Hours earned inside HIMS are verified automatically. External activity stays
                            pending until HR checks the certificate.
                        </small>
                    </div>

                    <div class="col-12" id="cpd-source-course" style="display:none">
                        <label class="hims-label" for="cpd_source_id_course">Course</label>
                        <select name="source_id" id="cpd_source_id_course" class="hims-input hims-select" disabled>
                            <option value="">Select a course…</option>
                            @foreach($courses as $course)
                            <option value="{{ $course->course_id }}" @selected(old('source_id') === $course->course_id)>
                                {{ $course->title }} ({{ $course->cpd_hours }}h)
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="cpd-source-training" style="display:none">
                        <label class="hims-label" for="cpd_source_id_training">Training Session</label>
                        <select name="source_id" id="cpd_source_id_training" class="hims-input hims-select" disabled>
                            <option value="">Select a session…</option>
                            @foreach($sessions as $session)
                            <option value="{{ $session->session_id }}" @selected(old('source_id') === $session->session_id)>
                                {{ $session->title }} — {{ $session->session_date }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="cpd_activity_name">Activity *</label>
                        <input type="text" name="activity_name" id="cpd_activity_name" class="hims-input" maxlength="300" required
                               value="{{ old('activity_name') }}" placeholder="e.g. PHA Annual Convention — Infection Control track">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cpd_cpd_hours">CPD Hours *</label>
                        <input type="number" name="cpd_hours" id="cpd_cpd_hours" class="hims-input" step="0.5" min="0.5" max="999.9" required
                               value="{{ old('cpd_hours') }}" placeholder="e.g. 8">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cpd_date_earned">Date Earned *</label>
                        <input type="date" name="date_earned" id="cpd_date_earned" class="hims-input" required
                               max="{{ now()->toDateString() }}" value="{{ old('date_earned', now()->toDateString()) }}">
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="cpd_renewal_period">Renewal Period</label>
                        <input type="text" name="renewal_period" id="cpd_renewal_period" class="hims-input" maxlength="20"
                               value="{{ old('renewal_period', now()->year) }}" placeholder="e.g. {{ now()->year }}">
                        <small style="color:#9ca3af;font-size:11.5px">
                            Which renewal window these hours count toward. Defaults to this year.
                        </small>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save CPD</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
// Only the picker matching the chosen source stays enabled, so exactly one
// source_id reaches the server — a disabled control is not submitted, which is
// what keeps two fields sharing one name unambiguous with no server unpicking.
(function () {
    const type = document.getElementById('cpd_source_type');
    if (!type) return;

    const course   = document.getElementById('cpd-source-course');
    const training = document.getElementById('cpd-source-training');

    function sync() {
        const v = type.value;
        course.style.display   = v === 'course'   ? '' : 'none';
        training.style.display = v === 'training' ? '' : 'none';
        document.getElementById('cpd_source_id_course').disabled   = v !== 'course';
        document.getElementById('cpd_source_id_training').disabled = v !== 'training';
    }

    type.addEventListener('change', sync);
    sync();
})();
</script>
@endpush

@if(request('new') === 'cpd' || ($errors->any() && old('_modal') === 'cpdCreateModal'))
@push('scripts')
<script>window.himsModal.open('cpdCreateModal');</script>
@endpush
@endif
