{{--
    Course editor, as a modal.

    Included once per page and re-targeted per row: clicking any button carrying
    data-course-edit rewrites this one form's action and fills its fields from the
    data-* attributes on that button, so a twenty-row catalogue still ships one
    form rather than twenty. Same shape as
    performance/cycles/_edit-modal.blade.php.

    Needs $competencies (competency_id, competency_name, competency_code,
    category_name) from the host controller, and each row's current tags as a
    comma-joined data-competencies string — $courseTags in LearningController::
    index() builds them in one query for the page.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@php $editOld = old('_modal') === 'courseEditModal'; @endphp
@push('modals')
<div class="hims-modal-backdrop" id="courseEditModal" role="dialog" aria-modal="true" aria-labelledby="courseEditTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="courseEditTitle"><i class="bi bi-pencil-square"></i> Edit Course</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" id="courseEditForm" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="courseEditModal">
            {{-- Not read by the controller. It exists so a failed validation
                 round-trip can put the form back on the right course: the POST
                 body is flashed, so old() hands the action back on reload. --}}
            <input type="hidden" name="_course_action" id="ce_action" value="{{ $editOld ? old('_course_action') : '' }}">
            <div class="hims-modal-body">
                @if($errors->any() && $editOld)
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="ce_title">Course Title *</label>
                        <input type="text" name="title" id="ce_title" class="hims-input" required maxlength="300"
                               value="{{ $editOld ? old('title') : '' }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ce_course_code">Course Code</label>
                        <input type="text" name="course_code" id="ce_course_code" class="hims-input" maxlength="30"
                               value="{{ $editOld ? old('course_code') : '' }}" placeholder="e.g. CLN-001">
                        <small style="color:#9ca3af;font-size:11.5px">Must be unique across the catalogue, or left blank.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ce_category">Category *</label>
                        <select name="category" id="ce_category" class="hims-input hims-select" required>
                            @foreach(['clinical'=>'Clinical','compliance'=>'Compliance','soft_skills'=>'Soft Skills','leadership'=>'Leadership','technical'=>'Technical','safety'=>'Safety'] as $value => $label)
                                <option value="{{ $value }}" @selected($editOld && old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ce_cpd_hours">CPD Hours *</label>
                        <input type="number" name="cpd_hours" id="ce_cpd_hours" class="hims-input" required min="0" step="0.5"
                               value="{{ $editOld ? old('cpd_hours') : '' }}">
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ce_difficulty_level">Difficulty Level *</label>
                        <select name="difficulty_level" id="ce_difficulty_level" class="hims-input hims-select" required>
                            @foreach(['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $value => $label)
                                <option value="{{ $value }}" @selected($editOld && old('difficulty_level') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ce_passing_score">Passing Score (%)</label>
                        <input type="number" name="passing_score" id="ce_passing_score" class="hims-input" min="0" max="100"
                               value="{{ $editOld ? old('passing_score') : '' }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ce_estimated_duration">Duration (minutes)</label>
                        <input type="number" name="estimated_duration" id="ce_estimated_duration" class="hims-input" min="1"
                               value="{{ $editOld ? old('estimated_duration') : '' }}" placeholder="e.g. 120">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ce_is_mandatory">Mandatory?</label>
                        <select name="is_mandatory" id="ce_is_mandatory" class="hims-input hims-select">
                            <option value="0">No</option>
                            <option value="1" @selected($editOld && old('is_mandatory'))>Yes</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="ce_description">Description</label>
                        <textarea name="description" id="ce_description" class="hims-input" rows="3">{{ $editOld ? old('description') : '' }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="hims-label">Remediates Competencies</label>
                        <small style="display:block;color:#9ca3af;font-size:11.5px;margin-bottom:8px">
                            Tagged courses show up as recommendations on gap analysis screens. Unticking
                            everything clears the course's tags.
                        </small>
                        @if(($competencies ?? collect())->isEmpty())
                            <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                                No competencies defined yet.
                            </div>
                        @else
                            <input type="search" class="hims-input hims-checklist-filter" data-checklist-filter="ce_competencies"
                                   placeholder="Filter competencies…" aria-label="Filter competencies">
                            <div class="hims-checklist" id="ce_competencies">
                                @foreach($competencies as $c)
                                    <label>
                                        <input type="checkbox" name="competencies[]" value="{{ $c->competency_id }}"
                                               @checked($editOld && in_array($c->competency_id, old('competencies', []), true))>
                                        <span>
                                            {{ $c->competency_name }}@if($c->competency_code) <span class="checklist-meta">({{ $c->competency_code }})</span>@endif
                                            @if($c->category_name)<br><span class="checklist-meta">{{ $c->category_name }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                                <div class="hims-checklist-empty" data-checklist-empty style="display:none">No competency matches that filter.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endpush

{{-- Opening, closing, Escape and backdrop dismissal come from the shared
     controller; the only thing particular to this modal is the per-row prefill. --}}
@include('partials.modal-js')
@include('partials.checklist-js')
@push('scripts')
<script>
(function () {
    const backdrop = document.getElementById('courseEditModal');
    if (!backdrop) return;

    const form   = document.getElementById('courseEditForm');
    const action = document.getElementById('ce_action');
    const fields = {
        title:              document.getElementById('ce_title'),
        course_code:        document.getElementById('ce_course_code'),
        category:           document.getElementById('ce_category'),
        cpd_hours:          document.getElementById('ce_cpd_hours'),
        difficulty_level:   document.getElementById('ce_difficulty_level'),
        passing_score:      document.getElementById('ce_passing_score'),
        estimated_duration: document.getElementById('ce_estimated_duration'),
        is_mandatory:       document.getElementById('ce_is_mandatory'),
        description:        document.getElementById('ce_description'),
    };

    // Every button carrying data-course-edit opens the one form, re-pointed.
    // Delegated off document so rows rendered later still work, and registered
    // after the shared controller so the modal is already open when we fill it.
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-course-edit]');
        if (!btn) return;

        form.action = btn.dataset.action;
        action.value = btn.dataset.action;
        Object.keys(fields).forEach(k => { fields[k].value = btn.dataset[k] || ''; });

        // Tags ride along as a comma-joined list. Clear first: the form is
        // shared, so ticks left by the previously opened row would otherwise be
        // saved onto this one.
        const list = document.getElementById('ce_competencies');
        if (list) {
            const tagged = (btn.dataset.competencies || '').split(',').filter(Boolean);
            list.querySelectorAll('input[type=checkbox]').forEach(box => {
                box.checked = tagged.includes(box.value);
            });

            // Reveal every row again — a filter typed for the last course would
            // otherwise still be hiding rows in this one. Dispatching input is
            // what re-runs the shared filter handler.
            const filter = document.querySelector('[data-checklist-filter="ce_competencies"]');
            if (filter && filter.value) {
                filter.value = '';
                filter.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    // A failed validation round-trip lands back here with the modal shut and the
    // user's input in old(); reopen so the errors are where they typed.
    @if($errors->any() && $editOld)
        if (action.value) { form.action = action.value; window.himsModal.open('courseEditModal'); }
    @endif
})();
</script>
@endpush
