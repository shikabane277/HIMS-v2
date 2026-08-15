@extends('layouts.hims')
@section('title','Session Feedback')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / Feedback')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('training.sessions.show', $session->session_id) }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-chat-square-text"></i> Session Feedback</h5></div>
            <div class="card-body">
                <div style="padding:16px;background:var(--hims-primary-xlight);border:1px solid var(--hims-primary-pale);border-radius:6px;margin-bottom:20px">
                    <div style="font-weight:600;margin-bottom:4px">{{ $session->title }}</div>
                    <div style="font-size:12.5px;color:#6b7280">{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }} · {{ $session->start_time }}–{{ $session->end_time }}</div>
                </div>

                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('training.sessions.feedback.store', $session->session_id) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Overall Rating *</label>
                            <div class="d-flex gap-2" style="margin-top:8px">
                                @for($i = 1; $i <= 5; $i++)
                                    <label style="cursor:pointer">
                                        <input type="radio" name="overall_rating" value="{{ $i }}" required @checked(old('overall_rating') == $i) style="display:none">
                                        <span class="rating-star" data-value="{{ $i }}" style="font-size:32px;color:#d1d5db">★</span>
                                    </label>
                                @endfor
                            </div>
                            <small style="color:#9ca3af;font-size:11.5px">How would you rate this session overall?</small>
                        </div>

                        <div class="col-md-4">
                            <label class="hims-label">Content Quality</label>
                            <select name="content_rating" class="hims-input hims-select">
                                <option value="">Not rated</option>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected(old('content_rating') == $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="hims-label">Instructor</label>
                            <select name="instructor_rating" class="hims-input hims-select">
                                <option value="">Not rated</option>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected(old('instructor_rating') == $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="hims-label">Venue</label>
                            <select name="venue_rating" class="hims-input hims-select">
                                <option value="">Not rated</option>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected(old('venue_rating') == $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="hims-label">Comments</label>
                            <textarea name="comments" class="hims-input" rows="5" placeholder="Share your thoughts on the session — what worked well, what could be improved...">{{ old('comments') }}</textarea>
                            <small style="color:#9ca3af;font-size:11.5px">Optional, but greatly appreciated by instructors and organisers.</small>
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('training.sessions.show', $session->session_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Submit Feedback</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var stars = document.querySelectorAll('.rating-star');
    var radios = document.querySelectorAll('input[name="overall_rating"]');

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
@endsection
