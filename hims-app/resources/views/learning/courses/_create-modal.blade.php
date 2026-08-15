{{--
    New-course form, as a modal. Replaces the standalone create page; the route,
    the POST and the validation are unchanged.

    Needs $competencies (competency_id, competency_name, competency_code,
    category_name) from the host controller.

    The competency tagging is a checkbox list rather than a <select multiple>:
    picking several out of a multi-select needs Ctrl/Cmd-click, which is not
    discoverable and is awkward on a laptop trackpad. The list carries a filter
    box because the catalogue outgrows a scroll box quickly.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="courseCreateModal" role="dialog" aria-modal="true" aria-labelledby="courseCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="courseCreateTitle"><i class="bi bi-plus-circle"></i> Create New Course</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('learning.courses.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="courseCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'courseCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="cr_title">Course Title *</label>
                        <input type="text" name="title" id="cr_title" class="hims-input" required
                               value="{{ old('title') }}" placeholder="e.g. Basic Life Support (BLS) Certification">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cr_course_code">Course Code</label>
                        <input type="text" name="course_code" id="cr_course_code" class="hims-input"
                               value="{{ old('course_code') }}" placeholder="e.g. CLN-001">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cr_category">Category *</label>
                        <select name="category" id="cr_category" class="hims-input hims-select" required>
                            <option value="">— Select —</option>
                            @foreach(['clinical'=>'Clinical','compliance'=>'Compliance','soft_skills'=>'Soft Skills','leadership'=>'Leadership','technical'=>'Technical','safety'=>'Safety'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="cr_cpd_hours">CPD Hours *</label>
                        <input type="number" name="cpd_hours" id="cr_cpd_hours" class="hims-input"
                               value="{{ old('cpd_hours', 1) }}" required min="0" step="0.5">
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="cr_difficulty_level">Difficulty Level *</label>
                        <select name="difficulty_level" id="cr_difficulty_level" class="hims-input hims-select" required>
                            @foreach(['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('difficulty_level', 'intermediate') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="cr_passing_score">Passing Score (%)</label>
                        <input type="number" name="passing_score" id="cr_passing_score" class="hims-input"
                               value="{{ old('passing_score', 70) }}" min="0" max="100">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cr_estimated_duration">Duration (minutes)</label>
                        <input type="number" name="estimated_duration" id="cr_estimated_duration" class="hims-input"
                               value="{{ old('estimated_duration') }}" min="1" placeholder="e.g. 120">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cr_is_mandatory">Mandatory?</label>
                        <select name="is_mandatory" id="cr_is_mandatory" class="hims-input hims-select">
                            <option value="0" @selected(! old('is_mandatory'))>No</option>
                            <option value="1" @selected(old('is_mandatory'))>Yes</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="cr_description">Description</label>
                        <textarea name="description" id="cr_description" class="hims-input" rows="3" placeholder="Course objectives and overview…">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="hims-label">Remediates Competencies</label>
                        <small style="display:block;color:#9ca3af;font-size:11.5px;margin-bottom:8px">
                            Tick the competencies this course is designed to address. Tagged courses show up as
                            recommendations on gap analysis screens.
                        </small>
                        @if(($competencies ?? collect())->isEmpty())
                            <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                                No competencies defined yet — you can tag this course later.
                            </div>
                        @else
                            <input type="search" class="hims-input hims-checklist-filter" data-checklist-filter="cr_competencies"
                                   placeholder="Filter competencies…" aria-label="Filter competencies">
                            <div class="hims-checklist" id="cr_competencies">
                                @foreach($competencies as $c)
                                    <label>
                                        <input type="checkbox" name="competencies[]" value="{{ $c->competency_id }}"
                                               @checked(in_array($c->competency_id, old('competencies', []), true))>
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
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Course</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
@include('partials.checklist-js')
@if(request('new') === 'course' || ($errors->any() && old('_modal') === 'courseCreateModal'))
@push('scripts')
<script>window.himsModal.open('courseCreateModal');</script>
@endpush
@endif
