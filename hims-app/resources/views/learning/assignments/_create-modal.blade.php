{{--
    Assign Training, as a modal. Replaces the standalone create page.

    Needs $courses, $sessions, $departments, $roles and $employees from the host
    controller (ComplianceController::index()).

    Two things changed along with the page-to-modal move:

    * The subject is now a multi-select. One act of requiring training usually
      covers a bundle — a new hire needs BLS *and* fire safety *and* the infection
      control session — and a single-select forced that to be three passes through
      the form, each re-picking the same target and the same deadline.
    * Courses and sessions are two separate .hims-checklist boxes rather than one
      grouped list, because partials/checklist-js toggles `display` on `label`
      elements only. A non-label group heading would survive filtering and strand
      itself over an empty group.

    Both boxes post into one `subjects[]` with prefixed composite values —
    `course:<uuid>` / `session:<uuid>` — since a flat list of bare UUIDs cannot say
    which table a row came from and a submission may mix both.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport rather than against the
    content wrapper's transform.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="assignmentCreateModal" role="dialog" aria-modal="true" aria-labelledby="assignmentCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="assignmentCreateTitle"><i class="bi bi-person-plus"></i> Assign Training</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('learning.assignments.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="assignmentCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'assignmentCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="hims-alert info mb-3">
                    <i class="bi bi-info-circle-fill"></i>
                    This is how somebody gets onto a course: it enrols them on their behalf and records a
                    deadline. Tick as many courses and sessions as the requirement covers — each one is
                    tracked and chased separately.
                </div>

                <div class="row g-3">
                    {{-- Courses first, and the search box first inside it:
                         himsModal.open() focuses the panel's first non-hidden
                         input, so leading with the checklist would land focus on
                         a checkbox. --}}
                    <div class="col-md-6">
                        <label class="hims-label">Courses</label>
                        @if($courses->isEmpty())
                            <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                                No active courses in the catalogue yet.
                            </div>
                        @else
                            <input type="search" class="hims-input hims-checklist-filter" data-checklist-filter="as_courses"
                                   placeholder="Search courses…" aria-label="Search courses">
                            <div class="hims-checklist" id="as_courses">
                                @foreach($courses as $course)
                                    <label>
                                        <input type="checkbox" name="subjects[]" value="course:{{ $course->course_id }}"
                                               @checked(in_array('course:'.$course->course_id, old('subjects', []), true))>
                                        <span>
                                            {{ $course->title }}
                                            <br><span class="checklist-meta">
                                                {{ ucfirst(str_replace('_',' ',$course->category)) }} ·
                                                {{ rtrim(rtrim(number_format($course->cpd_hours, 1), '0'), '.') }} CPD hrs
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                                <div class="hims-checklist-empty" data-checklist-empty style="display:none">No course matches that search.</div>
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label">Training sessions</label>
                        @if($sessions->isEmpty())
                            <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                                No upcoming sessions scheduled.
                            </div>
                        @else
                            <input type="search" class="hims-input hims-checklist-filter" data-checklist-filter="as_sessions"
                                   placeholder="Search sessions…" aria-label="Search training sessions">
                            <div class="hims-checklist" id="as_sessions">
                                @foreach($sessions as $session)
                                    <label>
                                        <input type="checkbox" name="subjects[]" value="session:{{ $session->session_id }}"
                                               @checked(in_array('session:'.$session->session_id, old('subjects', []), true))>
                                        <span>
                                            {{ $session->title }}
                                            <br><span class="checklist-meta">{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}</span>
                                        </span>
                                    </label>
                                @endforeach
                                <div class="hims-checklist-empty" data-checklist-empty style="display:none">No session matches that search.</div>
                            </div>
                            <small style="color:#9ca3af;font-size:11.5px">
                                Only upcoming sessions are listed. Capacity is respected — anyone beyond it is reported as skipped.
                            </small>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="as_target_type">Assign to *</label>
                        <select name="target_type" id="as_target_type" class="hims-input hims-select" required onchange="himsToggleAssignTarget()">
                            <option value="employee" @selected(old('target_type','employee') === 'employee')>One employee</option>
                            <option value="department" @selected(old('target_type') === 'department')>A department</option>
                            <option value="role" @selected(old('target_type') === 'role')>Everyone in a role</option>
                            <option value="all" @selected(old('target_type') === 'all')>All active employees</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div id="as_target_employee_wrap">
                            <label class="hims-label">Employee *</label>
                            <select name="target_id" class="hims-input hims-select" data-assign-target="employee">
                                <option value="">— Select an employee —</option>
                                @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}" @selected(old('target_id') === $employee->employee_id)>
                                    {{ $employee->last_name }}, {{ $employee->first_name }} ({{ $employee->employee_code }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="as_target_department_wrap" style="display:none">
                            <label class="hims-label">Department *</label>
                            <select name="target_id" class="hims-input hims-select" data-assign-target="department" disabled>
                                <option value="">— Select a department —</option>
                                @foreach($departments as $department)
                                <option value="{{ $department->department_id }}" @selected(old('target_id') === $department->department_id)>{{ $department->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="as_target_role_wrap" style="display:none">
                            <label class="hims-label">Role *</label>
                            <select name="target_id" class="hims-input hims-select" data-assign-target="role" disabled>
                                <option value="">— Select a role —</option>
                                @foreach($roles as $role)
                                <option value="{{ $role->role_id }}" @selected(old('target_id') === $role->role_id)>{{ $role->role_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="as_target_all_wrap" style="display:none">
                            <label class="hims-label">Scope</label>
                            <div style="padding:9px 0;font-size:13px;color:#6b7280">
                                Every employee with <strong>active</strong> employment status. Nobody who has left is included.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="as_required_by">Required By</label>
                        <input type="date" name="required_by" id="as_required_by" class="hims-input"
                               value="{{ old('required_by') }}" min="{{ now()->toDateString() }}">
                        <small style="color:#9ca3af;font-size:11.5px">Leave blank for no deadline. Past this date, anyone unfinished counts as overdue.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label" for="as_reason">Reason</label>
                        <input type="text" name="reason" id="as_reason" class="hims-input" value="{{ old('reason') }}"
                               maxlength="1000" placeholder="e.g. JCI IPSG.1 annual requirement">
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Assign</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
@include('partials.checklist-js')

@push('scripts')
{{-- Four target inputs share the name `target_id`, so all but the chosen one must
     be disabled — a disabled control is not submitted, which keeps the posted
     payload unambiguous without any server-side unpicking. Lives outside
     @push('modals') per the modal contract: only the backdrop belongs in the
     stack. --}}
<script>
function himsToggleAssignTarget() {
    var chosen = document.getElementById('as_target_type').value;
    ['employee','department','role','all'].forEach(function (type) {
        var wrap = document.getElementById('as_target_' + type + '_wrap');
        if (wrap) { wrap.style.display = (type === chosen) ? '' : 'none'; }
        var input = document.querySelector('[data-assign-target="' + type + '"]');
        if (input) { input.disabled = (type !== chosen); }
    });
}
himsToggleAssignTarget();
</script>
@endpush

@if(request('new') === 'assignment' || ($errors->any() && old('_modal') === 'assignmentCreateModal'))
@push('scripts')
<script>window.himsModal.open('assignmentCreateModal');</script>
@endpush
@endif
