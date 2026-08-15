{{--
    Session feedback, as a modal.

    Replaces training/sessions/feedback.blade.php and its GET route. Only
    included when $canGiveFeedback — the person attended and has not already
    submitted — which the session page computes once at the top of the file.

    The GET page it replaces carried the only friendly "you have already
    submitted feedback" check; storeFeedback() re-checked attendance but not
    that, and training_feedback is UNIQUE (session_id, employee_id), so a double
    submit would have become a 500. That guard moved into storeFeedback() with
    this conversion — a view that hides a button is not a server-side rule.

    Reached from the Feedback Summary button and from ?feedback=1.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="feedbackModal" role="dialog" aria-modal="true" aria-labelledby="feedbackModalTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="feedbackModalTitle"><i class="bi bi-chat-square-text"></i> Session Feedback</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('training.sessions.feedback.store', $session->session_id) }}">
            @csrf
            <input type="hidden" name="_modal" value="feedbackModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'feedbackModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                @php $isOld = $errors->any() && old('_modal') === 'feedbackModal'; @endphp

                <div style="padding:14px;background:var(--hims-primary-xlight);border:1px solid var(--hims-primary-pale);border-radius:6px;margin-bottom:18px">
                    <div style="font-weight:600;margin-bottom:4px">{{ $session->title }}</div>
                    <div style="font-size:12.5px;color:#6b7280">{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }} · {{ $session->start_time }}–{{ $session->end_time }}</div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label">Overall Rating *</label>
                        <div class="d-flex gap-2" style="margin-top:8px">
                            @for($i = 1; $i <= 5; $i++)
                                <label style="cursor:pointer">
                                    <input type="radio" name="overall_rating" value="{{ $i }}" required
                                           @checked($isOld && old('overall_rating') == $i) style="display:none">
                                    <span class="rating-star" data-value="{{ $i }}" style="font-size:32px;color:#d1d5db">★</span>
                                </label>
                            @endfor
                        </div>
                        <small style="color:#9ca3af;font-size:11.5px">How would you rate this session overall?</small>
                    </div>

                    @foreach(['content_rating' => 'Content Quality', 'instructor_rating' => 'Instructor', 'venue_rating' => 'Venue'] as $field => $label)
                    <div class="col-md-4">
                        <label class="hims-label" for="tf_{{ $field }}">{{ $label }}</label>
                        <select name="{{ $field }}" id="tf_{{ $field }}" class="hims-input hims-select">
                            <option value="">Not rated</option>
                            @for($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected($isOld && old($field) == $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                            @endfor
                        </select>
                    </div>
                    @endforeach

                    <div class="col-12">
                        <label class="hims-label" for="tf_comments">Comments</label>
                        <textarea name="comments" id="tf_comments" class="hims-input" rows="5" maxlength="2000"
                                  placeholder="Share your thoughts on the session — what worked well, what could be improved…">{{ $isOld ? old('comments') : '' }}</textarea>
                        <small style="color:#9ca3af;font-size:11.5px">Optional, but greatly appreciated by instructors and organisers.</small>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Submit Feedback</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
(function () {
    // Scoped to the modal: the star row is the only place on this page with
    // .rating-star, but a page-wide query would start matching the wrong widget
    // the moment a second modal grows one.
    var modal = document.getElementById('feedbackModal');
    if (! modal) return;

    var stars = modal.querySelectorAll('.rating-star');
    var radios = modal.querySelectorAll('input[name="overall_rating"]');

    function paint(n) {
        stars.forEach(function (star, i) {
            star.style.color = i < n ? '#f59e0b' : '#d1d5db';
        });
    }

    radios.forEach(function (radio) {
        if (radio.checked) paint(parseInt(radio.value, 10));
    });

    stars.forEach(function (star) {
        star.addEventListener('click', function () {
            var val = parseInt(this.getAttribute('data-value'), 10);
            radios[val - 1].checked = true;
            paint(val);
        });
    });
})();
</script>
@endpush

@if(request('feedback') || ($errors->any() && old('_modal') === 'feedbackModal'))
@push('scripts')
<script>window.himsModal.open('feedbackModal');</script>
@endpush
@endif
