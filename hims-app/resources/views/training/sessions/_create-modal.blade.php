{{--
    Schedule a training session, as a modal.

    Replaces training/sessions/create.blade.php and its GET route. Needs
    $activeVenues and $instructors, which training.index now selects for it —
    $activeVenues rather than the card's $venues, because an offline venue is
    listed on the page but must not be bookable. The deleted method also loaded
    $courses; the form never referenced them, so that query is gone rather than
    moved.

    Reached from the Training page's own button and from ?new=session — which is
    what the two dashboard quick actions now link to.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="sessionCreateModal" role="dialog" aria-modal="true" aria-labelledby="sessionCreateTitle">
    <div class="hims-modal" style="max-width:760px">
        <div class="hims-modal-header">
            <h5 id="sessionCreateTitle"><i class="bi bi-calendar-plus"></i> Schedule New Training Session</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('training.sessions.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="sessionCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'sessionCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                @php $isOld = $errors->any() && old('_modal') === 'sessionCreateModal'; @endphp

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="ts_title">Session Title *</label>
                        <input type="text" name="title" id="ts_title" class="hims-input" required maxlength="300"
                               value="{{ $isOld ? old('title') : '' }}" placeholder="e.g. Basic Life Support Refresher">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ts_category">Category *</label>
                        <select name="category" id="ts_category" class="hims-input hims-select" required>
                            <option value="">— Select —</option>
                            @foreach(['clinical' => 'Clinical', 'fire_safety' => 'Fire Safety', 'compliance' => 'Compliance', 'leadership' => 'Leadership', 'soft_skills' => 'Soft Skills', 'technical' => 'Technical'] as $value => $label)
                                <option value="{{ $value }}" @selected($isOld && old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ts_instructor_id">Instructor *</label>
                        <select name="instructor_id" id="ts_instructor_id" class="hims-input hims-select" required>
                            <option value="">— Select Instructor —</option>
                            @foreach($instructors ?? [] as $emp)
                                <option value="{{ $emp->employee_id }}" @selected($isOld && old('instructor_id') === $emp->employee_id)>
                                    {{ $emp->first_name }} {{ $emp->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ts_venue_id">Venue</label>
                        <select name="venue_id" id="ts_venue_id" class="hims-input hims-select">
                            <option value="">— Online / TBD —</option>
                            @foreach($activeVenues ?? [] as $v)
                                <option value="{{ $v->venue_id }}" @selected($isOld && old('venue_id') === $v->venue_id)>
                                    {{ $v->venue_name }} (cap: {{ $v->capacity }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ts_capacity">Max Capacity *</label>
                        <input type="number" name="capacity" id="ts_capacity" class="hims-input" required min="1"
                               value="{{ $isOld ? old('capacity', 30) : 30 }}">
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ts_session_date">Session Date *</label>
                        <input type="date" name="session_date" id="ts_session_date" class="hims-input" required
                               value="{{ $isOld ? old('session_date') : '' }}">
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ts_start_time">Start Time *</label>
                        <input type="time" name="start_time" id="ts_start_time" class="hims-input" required
                               value="{{ $isOld ? old('start_time') : '' }}">
                    </div>

                    <div class="col-md-4">
                        <label class="hims-label" for="ts_end_time">End Time *</label>
                        <input type="time" name="end_time" id="ts_end_time" class="hims-input" required
                               value="{{ $isOld ? old('end_time') : '' }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ts_cpd_hours">CPD Hours</label>
                        <input type="number" name="cpd_hours" id="ts_cpd_hours" class="hims-input" step="0.5" min="0"
                               value="{{ $isOld ? old('cpd_hours', 0) : 0 }}">
                        <small style="color:#9ca3af;font-size:11.5px">Credited automatically when an attendee is checked in.</small>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="ts_description">Description</label>
                        <textarea name="description" id="ts_description" class="hims-input" rows="3"
                                  placeholder="Session objectives and agenda…">{{ $isOld ? old('description') : '' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Schedule Session</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if(request('new') === 'session' || ($errors->any() && old('_modal') === 'sessionCreateModal'))
@push('scripts')
<script>window.himsModal.open('sessionCreateModal');</script>
@endpush
@endif
