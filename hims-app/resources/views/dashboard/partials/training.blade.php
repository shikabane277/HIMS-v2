{{-- Training Officer view: the hospital's learning programme. No performance or succession data. --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in"><div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-value">{{ $stats['sessions_upcoming'] }}</div><div class="stat-label">Sessions, next 30 days</div></div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in" style="animation-delay:.04s"><div class="stat-icon"><i class="bi bi-person-check"></i></div>
            <div class="stat-value">{{ $stats['attendance_rate'] !== null ? $stats['attendance_rate'].'%' : '—' }}</div><div class="stat-label">Attendance, last 90 days</div></div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in" style="animation-delay:.08s"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value" style="{{ $stats['mandatory_overdue'] ? 'color:#dc2626' : '' }}">{{ $stats['mandatory_overdue'] }}</div><div class="stat-label">Mandatory training overdue</div></div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in" style="animation-delay:.12s"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value" style="{{ $stats['cpd_pending'] ? 'color:#d97706' : '' }}">{{ $stats['cpd_pending'] }}</div><div class="stat-label">CPD certificates to verify</div></div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in" style="animation-delay:.16s"><div class="stat-icon"><i class="bi bi-patch-exclamation"></i></div>
            <div class="stat-value">{{ $stats['expiring_60'] }}</div><div class="stat-label">Certifications expiring ≤ 60 days</div></div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card animate-in" style="animation-delay:.2s"><div class="stat-icon"><i class="bi bi-journal-bookmark"></i></div>
            <div class="stat-value">{{ $stats['active_courses'] }}</div><div class="stat-label">Active courses</div></div>
    </div>
</div>

<div class="d-flex gap-2 mb-4" style="flex-wrap:wrap">
    <a href="{{ route('training.index', ['new' => 'session']) }}" class="btn-hims btn-hims-primary"><i class="bi bi-calendar-plus"></i> Schedule a session</a>
    <a href="{{ route('learning.assignments.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-list-check"></i> Mandatory training</a>
    <a href="{{ route('learning.cpd.index', ['filter' => 'pending']) }}" class="btn-hims btn-hims-outline"><i class="bi bi-patch-check"></i> Verify CPD</a>
    <a href="{{ route('learning.accreditation') }}" class="btn-hims btn-hims-outline"><i class="bi bi-clipboard-data"></i> Accreditation report</a>
    <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-bar-chart-steps"></i> Training needs</a>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-calendar-week"></i> Upcoming sessions</h5><a href="{{ route('training.index') }}" style="font-size:12.5px">All sessions</a></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Session</th><th>Date</th><th>Instructor</th><th>Seats</th></tr></thead>
                    <tbody>
                        @forelse($upcoming_sessions as $s)
                        <tr>
                            <td data-label="Session"><a href="{{ route('training.sessions.show', $s->session_id) }}" style="font-weight:600">{{ $s->title }}</a>
                                <div style="font-size:11.5px;color:#6b7280">{{ $s->venue_name ?? 'No venue' }}{{ (float) $s->cpd_hours > 0 ? ' · '.$s->cpd_hours.' CPD h' : '' }}</div></td>
                            <td data-label="Date" style="font-size:12.5px;white-space:nowrap">{{ \Carbon\Carbon::parse($s->session_date)->format('M d') }} · {{ substr((string) $s->start_time, 0, 5) }}</td>
                            <td data-label="Instructor" style="font-size:12.5px">{{ $s->instructor_name ?? '—' }}</td>
                            <td data-label="Seats" style="font-size:12.5px">{{ (int) $s->registered_count }} / {{ (int) $s->capacity }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:28px">No sessions scheduled.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="hims-card animate-in mb-3">
            <div class="card-header"><h5><i class="bi bi-alarm"></i> Overdue mandatory training</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Course</th><th>Due</th></tr></thead>
                    <tbody>
                        @forelse($overdue_training as $o)
                        <tr>
                            <td data-label="Employee"><strong>{{ $o->first_name }} {{ $o->last_name }}</strong><div style="font-size:11.5px;color:#6b7280">{{ $o->department_name }}</div></td>
                            <td data-label="Course" style="font-size:12.5px">{{ $o->title }}</td>
                            <td data-label="Due" style="font-size:12.5px;color:#dc2626;white-space:nowrap">{{ \Carbon\Carbon::parse($o->required_by)->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">Nobody is overdue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-patch-exclamation"></i> Certifications expiring soon</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Certification</th><th>Expires</th></tr></thead>
                    <tbody>
                        @forelse($expiring_credentials as $c)
                        <tr>
                            <td data-label="Employee"><strong>{{ $c->first_name }} {{ $c->last_name }}</strong></td>
                            <td data-label="Certification" style="font-size:12.5px">{{ $c->credential_type }}</td>
                            <td data-label="Expires" style="font-size:12.5px;white-space:nowrap">{{ \Carbon\Carbon::parse($c->expiry_date)->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">Nothing expires in the next 60 days.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
