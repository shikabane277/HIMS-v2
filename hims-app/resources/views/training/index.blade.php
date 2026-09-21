@extends('layouts.hims')
@section('title','Training Management')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training')
@section('content')
@include('partials.learning-tabs')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Training Calendar & Sessions</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Plan, schedule, and track in-hospital training sessions and attendance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('training.venues.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-building"></i> Venues</a>
        @can('manage-training')
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="sessionCreateModal"><i class="bi bi-plus-circle"></i> New Session</button>
        @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-calendar-event"></i></div><div class="stat-value">{{ $stats['upcoming_sessions'] ?? 0 }}</div><div class="stat-label">Upcoming Sessions</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-people"></i></div><div class="stat-value">{{ $stats['total_registrations'] ?? 0 }}</div><div class="stat-label">Total Registrations</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-value">{{ $stats['avg_attendance'] ?? 0 }}%</div><div class="stat-label">Avg Attendance</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-star"></i></div><div class="stat-value">{{ $stats['avg_feedback_score'] ?? '—' }}</div><div class="stat-label">Avg Feedback Score</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- Upcoming Sessions -->
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-calendar-event"></i> Upcoming Sessions</h5>
                <div class="d-flex gap-2">
                    <form method="GET" action="{{ route('training.index') }}">
                        <select name="category" class="hims-input hims-select" style="width:160px;padding:6px 12px;font-size:13px" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach($categories ?? [] as $cat)
                                <option value="{{ $cat }}" @selected(($category ?? '') === $cat)>{{ ucwords(str_replace('_',' ',$cat)) }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Session</th><th>Date & Time</th><th>Venue</th><th>Instructor</th><th>Seats</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($sessions ?? [] as $session)
                        <tr>
                            <td data-label="Session">
                                <div style="font-weight:600">{{ $session->title }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $session->session_code ?? '' }} · {{ $session->cpd_hours ?? 0 }} CPD hrs</div>
                            </td>
                            <td data-label="Date & Time" style="font-size:12.5px">
                                <strong>{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}</strong><br>
                                {{ $session->start_time }} – {{ $session->end_time }}
                            </td>
                            <td data-label="Venue">{{ $session->venue_name ?? 'Online' }}</td>
                            <td data-label="Instructor">{{ $session->instructor_name ?? '—' }}</td>
                            <td data-label="Seats">
                                <div style="font-size:12.5px">{{ $session->registered_count ?? 0 }}/{{ $session->capacity }}</div>
                                <div class="hims-progress mt-1" style="width:60px">
                                    <div class="hims-progress-bar" style="width:{{ $session->capacity > 0 ? min(100, round(($session->registered_count ?? 0)/$session->capacity*100)) : 0 }}%"></div>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <a href="{{ route('training.sessions.show', $session->session_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                                <form method="POST" action="{{ route('training.register', $session->session_id) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-plus-circle"></i> Register</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                            @if($category ?? null)
                                No upcoming sessions in this category.
                            @else
                                No upcoming sessions.
                                @can('manage-training')
                                    <button type="button" class="hims-link-button" data-modal-open="sessionCreateModal">Schedule one</button>.
                                @endcan
                            @endif
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Venues Panel -->
    <div class="col-lg-4">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-building-fill"></i> Venue Availability</h5>
                @can('manage-venues')
                {{-- Cross-page: the venue modal lives on the Venues page, and a
                     button here cannot open a modal there. --}}
                <a href="{{ route('training.venues.index', ['new' => 'venue']) }}" class="btn-hims btn-hims-primary btn-sm" title="Add a venue"><i class="bi bi-plus"></i></a>
                @endcan
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($venues ?? [] as $venue)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--hims-primary-pale);border-radius:8px;border:1px solid var(--hims-border)">
                    <div>
                        <div style="font-weight:600;font-size:13px">{{ $venue->venue_name }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $venue->building ?? '' }} · Cap: {{ $venue->capacity }}</div>
                    </div>
                    <span class="hims-badge {{ $venue->is_active ? 'green' : 'gray' }}">
                        {{ $venue->is_active ? 'Available' : 'Offline' }}
                    </span>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:24px">No venues configured.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Feedback Summary -->
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-chat-square-text"></i> Recent Session Feedback</h5>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Session</th><th>Employee</th><th>Overall</th><th>Content</th><th>Instructor</th><th>Sentiment</th></tr></thead>
            <tbody>
                @forelse($feedback ?? [] as $fb)
                <tr>
                    <td data-label="Session">{{ $fb->session_title ?? '—' }}</td>
                    <td data-label="Employee">{{ $fb->employee_name ?? '—' }}</td>
                    <td data-label="Overall">
                        <span class="star-rating">
                            @for($i=1;$i<=5;$i++)
                                <i class="bi bi-star-fill{{ $i > $fb->overall_rating ? ' empty' : '' }}"></i>
                            @endfor
                        </span>
                    </td>
                    <td data-label="Content">{{ $fb->content_rating ?? '—' }}/5</td>
                    <td data-label="Instructor">{{ $fb->instructor_rating ?? '—' }}/5</td>
                    <td data-label="Sentiment">
                        @if($fb->ai_sentiment_label)
                        <span class="hims-badge {{ $fb->ai_sentiment_label === 'positive' ? 'green' : ($fb->ai_sentiment_label === 'negative' ? 'red' : 'yellow') }}">
                            {{ ucfirst($fb->ai_sentiment_label) }}
                        </span>
                        @else
                        <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">No feedback submitted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('manage-training')
    @include('training.sessions._create-modal')
@endcan
@endsection
