@extends('layouts.hims')
@section('title','Training Management')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Training Calendar & Sessions</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Plan, schedule, and track in-hospital training sessions and attendance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('training.venues.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-building"></i> Venues</a>
        <a href="{{ route('training.sessions.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-plus-circle"></i> New Session</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value">{{ $stats['upcoming_sessions'] ?? 0 }}</div><div class="stat-label">Upcoming Sessions</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">{{ $stats['total_registrations'] ?? 0 }}</div><div class="stat-label">Total Registrations</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $stats['avg_attendance'] ?? 0 }}%</div><div class="stat-label">Avg Attendance</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value">{{ $stats['avg_feedback_score'] ?? '—' }}</div><div class="stat-label">Avg Feedback Score</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- Upcoming Sessions -->
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-calendar-event"></i> Upcoming Sessions</h5>
                <div class="d-flex gap-2">
                    <select class="hims-input hims-select" style="width:140px;padding:6px 12px;font-size:13px">
                        <option>All Categories</option>
                        <option>Clinical</option>
                        <option>Fire Safety</option>
                        <option>Leadership</option>
                    </select>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Session</th><th>Date & Time</th><th>Venue</th><th>Instructor</th><th>Seats</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($sessions ?? [] as $session)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $session->title }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $session->session_code ?? '' }} · {{ $session->cpd_hours ?? 0 }} CPD hrs</div>
                            </td>
                            <td style="font-size:12.5px">
                                <strong>{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}</strong><br>
                                {{ $session->start_time }} – {{ $session->end_time }}
                            </td>
                            <td>{{ $session->venue_name ?? 'Online' }}</td>
                            <td>{{ $session->instructor_name ?? '—' }}</td>
                            <td>
                                <div style="font-size:12.5px">{{ $session->registered_count ?? 0 }}/{{ $session->capacity }}</div>
                                <div class="hims-progress mt-1" style="width:60px">
                                    <div class="hims-progress-bar" style="width:{{ $session->capacity > 0 ? min(100, round(($session->registered_count ?? 0)/$session->capacity*100)) : 0 }}%"></div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('training.sessions.show', $session->session_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                                <a href="{{ route('training.register', $session->session_id) }}" class="btn-hims btn-hims-primary btn-sm">Register</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">No upcoming sessions. <a href="{{ route('training.sessions.create') }}" class="text-primary-hims">Schedule one</a>.</td></tr>
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
                <a href="{{ route('training.venues.create') }}" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-plus"></i></a>
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
                    <td>{{ $fb->session_title ?? '—' }}</td>
                    <td>{{ $fb->employee_name ?? '—' }}</td>
                    <td>
                        <span class="star-rating">
                            @for($i=1;$i<=5;$i++)
                                <i class="bi bi-star-fill{{ $i > $fb->overall_rating ? ' empty' : '' }}"></i>
                            @endfor
                        </span>
                    </td>
                    <td>{{ $fb->content_rating ?? '—' }}/5</td>
                    <td>{{ $fb->instructor_rating ?? '—' }}/5</td>
                    <td>
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
@endsection
