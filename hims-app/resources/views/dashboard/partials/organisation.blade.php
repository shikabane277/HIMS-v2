{{-- Admin / HR manager view: whole-organisation workforce decision support. --}}

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $stats['headcount'] ?? 0 }}</div>
            <div class="stat-label">Active Employees</div>
            <div class="stat-change up"><i class="bi bi-people"></i> {{ ($headcount_by_department ?? collect())->count() }} departments</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.06s">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $stats['pending_reviews'] ?? 0 }}</div>
            <div class="stat-label">Reviews In Progress</div>
            <div class="stat-change up"><i class="bi bi-arrow-up-right"></i> Not yet completed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.12s">
            <div class="stat-icon">🔴</div>
            <div class="stat-value">{{ $stats['expiring_credentials'] ?? 0 }}</div>
            <div class="stat-label">Expiring Licenses</div>
            <div class="stat-change down"><i class="bi bi-exclamation-triangle"></i> {{ $stats['expired_credentials'] ?? 0 }} already expired</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">🎯</div>
            <div class="stat-value">{{ $stats['critical_gaps'] ?? 0 }}</div>
            <div class="stat-label">Critical Skill Gaps</div>
            <div class="stat-change down"><i class="bi bi-dash-circle"></i> 2+ levels below required</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in">
            <div class="stat-icon">🎓</div>
            <div class="stat-value">{{ $stats['active_enrollments'] ?? 0 }}</div>
            <div class="stat-label">Active Enrollments</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.06s">
            <div class="stat-icon">📅</div>
            <div class="stat-value">{{ $stats['upcoming_sessions'] ?? 0 }}</div>
            <div class="stat-label">Upcoming Sessions</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.12s">
            <div class="stat-icon">⭐</div>
            <div class="stat-value">{{ $stats['recognitions_this_month'] ?? 0 }}</div>
            <div class="stat-label">Recognitions This Month</div>
        </div>
    </div>
    @if(isset($system))
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">🔐</div>
            <div class="stat-value">{{ $system['user_accounts'] ?? 0 }}</div>
            <div class="stat-label">Login Accounts</div>
            @if(($system['unlinked_accounts'] ?? 0) > 0)
            <div class="stat-change down"><i class="bi bi-link-45deg"></i> {{ $system['unlinked_accounts'] }} not linked to staff</div>
            @endif
        </div>
    </div>
    @elseif(isset($unassessed_employees))
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">📝</div>
            <div class="stat-value">{{ $unassessed_employees }}</div>
            <div class="stat-label">Never Assessed</div>
            <div class="stat-change down"><i class="bi bi-exclamation-circle"></i> No competency record</div>
        </div>
    </div>
    @endif
</div>

<div class="row g-3 mb-4">
    {{-- Headcount by department --}}
    <div class="col-lg-5">
        <div class="hims-card animate-in">
            <div class="card-header"><h5><i class="bi bi-building"></i> Workforce by Department</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                @php $maxHead = max(1, ($headcount_by_department ?? collect())->max('headcount') ?? 1); @endphp
                @forelse($headcount_by_department ?? [] as $dept)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                        <span style="font-weight:600">{{ $dept->name }}</span>
                        <span style="color:#6b7280">{{ $dept->headcount }}</span>
                    </div>
                    <div style="height:7px;background:#f1f5f9;border-radius:6px;overflow:hidden">
                        <div style="height:100%;width:{{ round(($dept->headcount / $maxHead) * 100) }}%;background:var(--hims-primary);border-radius:6px"></div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No departments yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Weakest competencies org-wide --}}
    <div class="col-lg-7">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header">
                <h5><i class="bi bi-graph-down-arrow"></i> Largest Skill Gaps</h5>
                @can('run-gap-analysis')
                <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-ghost btn-sm">AI Analysis</a>
                @endcan
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Competency</th><th>Required</th><th>Avg</th><th>Gap</th><th>Assessed</th></tr></thead>
                    <tbody>
                        @forelse($competency_hotspots ?? [] as $row)
                        <tr>
                            <td><strong>{{ $row->competency_name }}</strong></td>
                            <td>{{ $row->required_proficiency }}/5</td>
                            <td>{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                            <td><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                            <td>{{ $row->assessed }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:28px">No gaps recorded — every assessed competency meets its requirement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="hims-card animate-in">
            <div class="card-header">
                <h5><i class="bi bi-activity"></i> Recent Performance Reviews</h5>
                <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Cycle</th><th>Status</th><th>Score</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($recent_reviews ?? [] as $review)
                        <tr>
                            <td><strong>{{ $review->employee_name ?? 'N/A' }}</strong></td>
                            <td>{{ $review->cycle_name ?? '—' }}</td>
                            <td>
                                <span class="hims-badge {{ $review->status === 'completed' ? 'green' : ($review->status === 'draft' ? 'gray' : 'yellow') }}">
                                    {{ ucfirst(str_replace('_',' ',$review->status ?? 'draft')) }}
                                </span>
                            </td>
                            <td>{{ $review->overall_score ? number_format($review->overall_score,2).'/5.00' : '—' }}</td>
                            <td><a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No reviews yet.</td></tr>
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
                @can('manage-review-cycles')
                <a href="{{ route('performance.cycles.create') }}" class="btn-hims btn-hims-primary" style="justify-content:center"><i class="bi bi-plus-circle"></i> New Review Cycle</a>
                @endcan
                @can('manage-performance')
                <a href="{{ route('performance.reviews.create') }}" class="btn-hims btn-hims-outline" style="justify-content:center"><i class="bi bi-clipboard-check"></i> Start a Review</a>
                @endcan
                @can('manage-competency')
                <a href="{{ route('competency.assessments.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-bullseye"></i> Record Assessment</a>
                @endcan
                @can('manage-training')
                <a href="{{ route('training.sessions.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-calendar-plus"></i> Schedule Training</a>
                @endcan
                <a href="{{ route('recognition.posts.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-star"></i> Give Recognition</a>
                @can('manage-succession')
                <a href="{{ route('succession.candidates.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-person-plus"></i> Nominate Candidate</a>
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="hims-card animate-in">
            <div class="card-header">
                <h5>⚠️ Succession Risk</h5>
                @can('view-succession')
                <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
                @endcan
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Position</th><th>Risk</th><th>Cover</th></tr></thead>
                    <tbody>
                        @forelse($risk_positions ?? [] as $pos)
                        <tr>
                            <td>
                                <strong>{{ $pos->position_title }}</strong>
                                <div style="font-size:11px;color:#9ca3af">{{ $pos->department_name ?? '—' }}</div>
                            </td>
                            <td>
                                <span class="hims-badge {{ $pos->vacancy_risk === 'critical' ? 'red' : ($pos->vacancy_risk === 'high' ? 'yellow' : 'green') }}">
                                    {{ ucfirst($pos->vacancy_risk) }}
                                </span>
                            </td>
                            <td>
                                <span class="hims-badge {{ ($pos->candidates ?? 0) > 0 ? 'green' : 'red' }}">
                                    {{ $pos->candidates ?? 0 }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">No critical positions defined.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card animate-in" style="animation-delay:.06s">
            <div class="card-header">
                <h5>🪪 Credential Alerts</h5>
                <a href="{{ route('competency.credentials.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
            </div>
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
                <div style="text-align:center;color:#9ca3af;padding:20px">All credentials current ✅</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card animate-in" style="animation-delay:.12s">
            <div class="card-header">
                <h5>❤️ Latest Recognition</h5>
                <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost btn-sm">Wall</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($recent_recognition ?? [] as $post)
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <div style="width:36px;height:36px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">{{ $post->badge_icon ?? '⭐' }}</div>
                    <div>
                        <div style="font-size:13px;font-weight:600">{{ $post->author_name }} → {{ $post->recipient_name }}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ Str::limit($post->message ?? '', 80) }}</div>
                        @if($post->badge_name)<span class="recognition-badge-pill mt-1">{{ $post->badge_name }}</span>@endif
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No recognition posts yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
