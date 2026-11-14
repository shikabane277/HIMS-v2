@extends('layouts.hims')
@section('title','Training Management')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training')
@section('content')
@include('partials.training-tabs')
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

{{-- Trainee / Employee Personal Training Status --}}
@if(isset($myRegistrations) && $myRegistrations->isNotEmpty())
<div class="hims-card mb-4" style="border:1px solid #059669;box-shadow:0 4px 12px rgba(5,150,105,0.08)">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:#f0fdf4">
        <div>
            <h5 style="color:#065f46;margin:0"><i class="bi bi-person-check-fill text-success"></i> My Training Status &amp; Sessions to Attend</h5>
            <small style="color:#047857">Your upcoming enrolled training sessions and attendance records</small>
        </div>
        <span class="hims-badge green">{{ $myRegistrations->count() }} Enrolled</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Session Code &amp; Title</th>
                    <th>Date &amp; Time</th>
                    <th>Venue</th>
                    <th>CPD Credit</th>
                    <th>Attendance Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($myRegistrations as $reg)
                @php
                    $isPast = \Carbon\Carbon::parse($reg->session_date)->isPast();
                    $badgeTone = match($reg->status) {
                        'attended' => 'green',
                        'registered' => ($isPast ? 'yellow' : 'blue'),
                        'waitlisted' => 'yellow',
                        'cancelled' => 'red',
                        default => 'gray'
                    };
                    $statusLabel = match($reg->status) {
                        'attended' => 'Attended ✓',
                        'registered' => ($isPast ? 'Pending Check-in' : 'Needs to Attend'),
                        'waitlisted' => 'Waitlisted',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($reg->status)
                    };
                @endphp
                <tr>
                    <td data-label="Session">
                        <div style="font-weight:700;color:var(--hims-primary)">{{ $reg->session_title }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $reg->session_code ?? 'SESSION' }}</div>
                    </td>
                    <td data-label="Date & Time" style="font-size:12.5px">
                        <strong>{{ \Carbon\Carbon::parse($reg->session_date)->format('M d, Y') }}</strong><br>
                        <span style="color:#6b7280">{{ $reg->start_time }} – {{ $reg->end_time }}</span>
                    </td>
                    <td data-label="Venue">
                        <i class="bi bi-geo-alt text-muted"></i> {{ $reg->venue_name ?? 'Hospital Main Venue' }}
                    </td>
                    <td data-label="CPD Credit">
                        <span class="hims-badge blue">{{ $reg->cpd_hours ?? 0 }} hrs</span>
                    </td>
                    <td data-label="Attendance Status">
                        <span class="hims-badge {{ $badgeTone }}">
                            <i class="bi {{ $reg->status === 'attended' ? 'bi-check-circle-fill' : 'bi-clock-fill' }}"></i>
                            {{ $statusLabel }}
                        </span>
                        @if($reg->required_by)
                            <div style="font-size:11px;color:#dc2626;margin-top:2px">Required by: {{ $reg->required_by }}</div>
                        @endif
                    </td>
                    <td data-label="Action">
                        <a href="{{ route('training.sessions.show', $reg->session_id) }}" class="btn-hims btn-hims-ghost btn-sm">
                            <i class="bi bi-box-arrow-up-right"></i> Details
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Training Analytics & Trends --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-people-fill text-primary-hims"></i> Session Attendance vs Capacity</h5>
                <span class="hims-badge green">Turnout & Participation</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px">
                    <canvas id="trainingAttendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-star-fill text-primary-hims"></i> Evaluation & Satisfaction Ratings</h5>
                <span class="hims-badge blue">Out of 5.00</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px">
                    <canvas id="trainingFeedbackChart"></canvas>
                </div>
            </div>
        </div>
    </div>
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
                                @php
                                    $isAlreadyRegistered = isset($myRegistrations) && $myRegistrations->contains('session_id', $session->session_id);
                                @endphp
                                @if($isAlreadyRegistered)
                                    <span class="btn-hims btn-hims-ghost btn-sm text-success" style="cursor:default">
                                        <i class="bi bi-check2-circle"></i> Enrolled
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('training.register', $session->session_id) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-plus-circle"></i> Register</button>
                                    </form>
                                @endif
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    // 1. Training Attendance & Registration Chart
    const attCanvas = document.getElementById('trainingAttendanceChart');
    if (attCanvas) {
        const catLabels = {!! json_encode($trainingCatLabels ?? []) !!};
        const catRegistered = {!! json_encode($trainingCatRegistered ?? []) !!};
        const catAttended = {!! json_encode($trainingCatAttended ?? []) !!};

        new Chart(attCanvas, {
            type: 'bar',
            data: {
                labels: catLabels.length ? catLabels : ['Clinical Skills', 'Infection Control', 'Emergency Care', 'BLS / ACLS', 'Patient Safety'],
                datasets: [
                    {
                        label: 'Registered',
                        data: catLabels.length ? catRegistered : [35, 42, 28, 50, 30],
                        backgroundColor: 'rgba(2, 132, 199, 0.3)',
                        borderColor: '#0284c7',
                        borderWidth: 1.5,
                        borderRadius: 5,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Attended',
                        data: catLabels.length ? catAttended : [32, 38, 25, 47, 28],
                        backgroundColor: 'rgba(5, 150, 105, 0.85)',
                        borderColor: '#059669',
                        borderWidth: 1.5,
                        borderRadius: 5,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter' } }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Feedback Ratings Horizontal Bar Chart
    const fbCanvas = document.getElementById('trainingFeedbackChart');
    if (fbCanvas) {
        const metrics = {!! json_encode($feedbackMetrics ?? []) !!};
        const metricLabels = Object.keys(metrics);
        const metricScores = Object.values(metrics);

        new Chart(fbCanvas, {
            type: 'bar',
            data: {
                labels: metricLabels.length ? metricLabels : ['Content Quality', 'Instructor Delivery', 'Practical Skills', 'Overall Rating'],
                datasets: [{
                    label: 'Rating (out of 5.00)',
                    data: metricScores.length ? metricScores : [4.5, 4.7, 4.3, 4.6],
                    backgroundColor: ['#059669', '#0284c7', '#8b5cf6', '#d97706'],
                    borderRadius: 6,
                    barPercentage: 0.55
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' Score: ' + ctx.parsed.x + ' / 5.00';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        min: 0,
                        max: 5,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
