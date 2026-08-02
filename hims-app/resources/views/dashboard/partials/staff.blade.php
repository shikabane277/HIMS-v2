{{-- Staff view: strictly the signed-in employee's own development picture. --}}

@if(empty($stats))
<div class="hims-card">
    <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
        <div style="font-size:48px;margin-bottom:12px">🔗</div>
        <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No employee profile linked</div>
        <p style="font-size:13px;max-width:440px;margin:0 auto">Your login is not linked to an employee record yet, so your performance, training and recognition history cannot be shown. Ask HR to link your account.</p>
    </div>
</div>
@else

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in">
            <div class="stat-icon">⭐</div>
            <div class="stat-value">{{ $stats['latest_score'] ? number_format($stats['latest_score'],2) : '—' }}</div>
            <div class="stat-label">Latest Review Score</div>
            <div class="stat-change up"><i class="bi bi-bar-chart"></i> Out of 5.00</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.06s">
            <div class="stat-icon">🎯</div>
            <div class="stat-value">{{ $stats['open_gaps'] ?? 0 }}</div>
            <div class="stat-label">Open Skill Gaps</div>
            <div class="stat-change {{ ($stats['open_gaps'] ?? 0) > 0 ? 'down' : 'up' }}"><i class="bi bi-bullseye"></i> Below required level</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.12s">
            <div class="stat-icon">📚</div>
            <div class="stat-value">{{ $stats['courses_active'] ?? 0 }}</div>
            <div class="stat-label">Courses In Progress</div>
            <div class="stat-change up"><i class="bi bi-check2-circle"></i> {{ $stats['courses_completed'] ?? 0 }} completed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">🕐</div>
            <div class="stat-value">{{ $stats['cpd_hours_year'] ?? 0 }}</div>
            <div class="stat-label">CPD Hours (12 mo)</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-bullseye"></i> My Skill Gaps</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Competency</th><th>Required</th><th>Current</th><th>Gap</th><th>Assessed</th></tr></thead>
                    <tbody>
                        @forelse($my_gaps ?? [] as $gap)
                        <tr>
                            <td><strong>{{ $gap->competency_name }}</strong></td>
                            <td>{{ $gap->required_proficiency }}/5</td>
                            <td>{{ $gap->current_proficiency }}/5</td>
                            <td><span class="gap-chip negative">{{ $gap->gap }}</span></td>
                            <td style="font-size:12px;color:#6b7280">{{ $gap->assessed_date }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:28px">No open gaps — you meet every assessed requirement ✅</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header">
                <h5><i class="bi bi-journal-bookmark"></i> My Learning</h5>
                <a href="{{ route('learning.index') }}" class="btn-hims btn-hims-ghost btn-sm">Catalogue</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($my_courses ?? [] as $course)
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                        <span style="font-size:12.5px;font-weight:600">{{ Str::limit($course->title, 42) }}</span>
                        <span class="hims-badge {{ $course->status === 'completed' ? 'green' : 'yellow' }}">{{ ucfirst(str_replace('_',' ',$course->status)) }}</span>
                    </div>
                    <div style="height:7px;background:#f1f5f9;border-radius:6px;overflow:hidden">
                        <div style="height:100%;width:{{ (int) $course->progress_pct }}%;background:var(--hims-primary);border-radius:6px"></div>
                    </div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:3px">{{ (int) $course->progress_pct }}% · {{ $course->cpd_hours }} CPD hrs</div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">
                    Not enrolled in any courses.
                    <div class="mt-2"><a href="{{ route('learning.index') }}" class="btn-hims btn-hims-primary btn-sm">Browse Courses</a></div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-clipboard-data"></i> My Reviews</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Cycle</th><th>Status</th><th>Score</th></tr></thead>
                    <tbody>
                        @forelse($my_reviews ?? [] as $review)
                        <tr>
                            <td>
                                <a href="{{ route('performance.show', $review->review_id) }}" style="font-weight:600;color:var(--hims-primary);text-decoration:none">{{ $review->cycle_name }}</a>
                            </td>
                            <td><span class="hims-badge {{ $review->status === 'completed' ? 'green' : 'yellow' }}">{{ ucfirst(str_replace('_',' ',$review->status)) }}</span></td>
                            <td>{{ $review->overall_score ? number_format($review->overall_score,2) : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">No reviews yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header"><h5>🪪 My Credentials</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @forelse($my_credentials ?? [] as $cred)
                @php
                    $expired = $cred->expiry_date && $cred->expiry_date < now()->toDateString();
                    $soon    = ! $expired && $cred->expiry_date && $cred->expiry_date <= now()->addDays(30)->toDateString();
                @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 11px;background:{{ $expired ? '#fee2e2' : ($soon ? '#fef3c7' : '#f0fdf4') }};border-radius:8px">
                    <div>
                        <div style="font-size:12.5px;font-weight:600">{{ $cred->credential_type }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $cred->issuing_body ?? '—' }}</div>
                        <div style="font-size:11px;margin-top:2px">Expires <strong>{{ $cred->expiry_date ?? '—' }}</strong></div>
                    </div>
                    <span class="hims-badge {{ $expired ? 'red' : ($soon ? 'yellow' : 'green') }}">
                        {{ $expired ? 'Expired' : ($soon ? 'Soon' : 'Valid') }}
                    </span>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No credentials on record.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card animate-in" style="animation-delay:.12s">
            <div class="card-header">
                <h5>❤️ My Recognition</h5>
                <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost btn-sm">Wall</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($my_recognition ?? [] as $post)
                <div style="display:flex;gap:11px;align-items:flex-start">
                    <div style="width:34px;height:34px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0">{{ $post->badge_icon ?? '⭐' }}</div>
                    <div>
                        <div style="font-size:12.5px;font-weight:600">From {{ $post->author_name }}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ Str::limit($post->message, 80) }}</div>
                        @if($post->badge_name)<span class="recognition-badge-pill mt-1">{{ $post->badge_name }}</span>@endif
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No recognition received yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if(($upcoming_sessions ?? collect())->isNotEmpty())
<div class="hims-card animate-in mt-3">
    <div class="card-header">
        <h5><i class="bi bi-calendar-event"></i> Upcoming Training Sessions</h5>
        <a href="{{ route('training.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Session</th><th>Date</th><th>Venue</th><th>CPD</th><th></th></tr></thead>
            <tbody>
                @foreach($upcoming_sessions as $session)
                <tr>
                    <td><strong>{{ $session->title }}</strong></td>
                    <td>{{ $session->session_date }}</td>
                    <td>{{ $session->venue_name ?? '—' }}</td>
                    <td>{{ $session->cpd_hours }}</td>
                    <td><a href="{{ route('training.sessions.show', $session->session_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endif
