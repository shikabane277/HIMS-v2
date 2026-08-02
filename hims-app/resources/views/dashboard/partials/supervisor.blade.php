{{-- Department head view: their own team only. --}}

@if(empty($stats))
<div class="hims-card">
    <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
        <div style="font-size:48px;margin-bottom:12px">🏢</div>
        <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No department linked</div>
        <p style="font-size:13px;max-width:420px;margin:0 auto">Your account is not linked to an employee profile with a department, so there is no team to show. Ask an administrator to link your account.</p>
    </div>
</div>
@else

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $stats['team_size'] ?? 0 }}</div>
            <div class="stat-label">Team Members</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.06s">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $stats['pending_reviews'] ?? 0 }}</div>
            <div class="stat-label">Reviews In Progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.12s">
            <div class="stat-icon">🎯</div>
            <div class="stat-value">{{ $stats['critical_gaps'] ?? 0 }}</div>
            <div class="stat-label">Critical Skill Gaps</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">⭐</div>
            <div class="stat-value">{{ $stats['avg_team_score'] ? number_format($stats['avg_team_score'],2) : '—' }}</div>
            <div class="stat-label">Avg Team Score</div>
            <div class="stat-change up"><i class="bi bi-bar-chart"></i> Out of 5.00</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="hims-card animate-in">
            <div class="card-header">
                <h5><i class="bi bi-people-fill"></i> My Team</h5>
                @can('view-employees')
                <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost btn-sm">Directory</a>
                @endcan
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Role</th><th>Latest Score</th><th>Avg Gap</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($team ?? [] as $member)
                        <tr>
                            <td>
                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                <div style="font-size:11px;color:#9ca3af">{{ $member->position_title ?? '—' }}</div>
                            </td>
                            <td>{{ $member->role_name }}</td>
                            <td>{{ $member->latest_score ? number_format($member->latest_score,2) : '—' }}</td>
                            <td>
                                @if($member->assessments == 0)
                                    <span class="hims-badge gray">Not assessed</span>
                                @else
                                    <span class="gap-chip {{ (float) $member->avg_gap >= 0 ? 'positive' : 'negative' }}">
                                        {{ (float) $member->avg_gap >= 0 ? '+' : '' }}{{ number_format((float) $member->avg_gap, 2) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @can('run-gap-analysis')
                                <a href="{{ route('competency.gap.employee', $member->employee_id) }}" class="btn-hims btn-hims-ghost btn-sm">Gap Analysis</a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No active team members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header"><h5>🚀 Quick Actions</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @can('manage-performance')
                <a href="{{ route('performance.reviews.create') }}" class="btn-hims btn-hims-primary" style="justify-content:center"><i class="bi bi-clipboard-check"></i> Start a Review</a>
                @endcan
                @can('manage-competency')
                <a href="{{ route('competency.assessments.create') }}" class="btn-hims btn-hims-outline" style="justify-content:center"><i class="bi bi-bullseye"></i> Record Assessment</a>
                @endcan
                @can('run-gap-analysis')
                <a href="{{ route('competency.gap.department') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-robot"></i> Department Gap Analysis</a>
                @endcan
                @can('manage-training')
                <a href="{{ route('training.sessions.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-calendar-plus"></i> Schedule Training</a>
                @endcan
                <a href="{{ route('recognition.posts.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-star"></i> Give Recognition</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-graph-down-arrow"></i> Team Skill Gaps</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Competency</th><th>Required</th><th>Avg</th><th>Gap</th></tr></thead>
                    <tbody>
                        @forelse($competency_hotspots ?? [] as $row)
                        <tr>
                            <td><strong>{{ $row->competency_name }}</strong></td>
                            <td>{{ $row->required_proficiency }}/5</td>
                            <td>{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                            <td><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:24px">No gaps recorded for your team.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header"><h5>🪪 Team Credential Alerts</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @forelse($credential_alerts ?? [] as $cred)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 11px;background:{{ $cred->status === 'expired' ? '#fee2e2' : '#fef3c7' }};border-radius:8px">
                    <div>
                        <div style="font-size:12.5px;font-weight:600">{{ $cred->employee_name }}</div>
                        <div style="font-size:11.5px;color:#6b7280">{{ $cred->credential_type }}</div>
                        <div style="font-size:11px;margin-top:2px">Expires <strong>{{ $cred->expiry_date }}</strong></div>
                    </div>
                    <span class="hims-badge {{ $cred->status === 'expired' ? 'red' : 'yellow' }}">
                        {{ $cred->status === 'expired' ? 'Expired' : 'Soon' }}
                    </span>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">All team credentials current ✅</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif
