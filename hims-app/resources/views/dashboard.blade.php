@extends('layouts.hims')
@section('title','Dashboard')
@section('page-title','Dashboard')
@section('breadcrumb','HIMS / Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $stats['pending_reviews'] ?? 0 }}</div>
            <div class="stat-label">Pending Reviews</div>
            <div class="stat-change up"><i class="bi bi-arrow-up-right"></i> Active cycles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.06s">
            <div class="stat-icon">🔴</div>
            <div class="stat-value">{{ $stats['expiring_credentials'] ?? 0 }}</div>
            <div class="stat-label">Expiring Licenses</div>
            <div class="stat-change down"><i class="bi bi-exclamation-triangle"></i> Within 30 days</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.12s">
            <div class="stat-icon">🎓</div>
            <div class="stat-value">{{ $stats['active_enrollments'] ?? 0 }}</div>
            <div class="stat-label">Active Enrollments</div>
            <div class="stat-change up"><i class="bi bi-arrow-up-right"></i> Courses in progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.18s">
            <div class="stat-icon">⭐</div>
            <div class="stat-value">{{ $stats['recognitions_this_month'] ?? 0 }}</div>
            <div class="stat-label">Recognitions This Month</div>
            <div class="stat-change up"><i class="bi bi-heart-fill"></i> Peer kudos</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="hims-card animate-in" style="animation-delay:.24s">
            <div class="card-header">
                <h5><i class="bi bi-activity"></i> Recent Performance Reviews</h5>
                <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr><th>Employee</th><th>Cycle</th><th>Status</th><th>Score</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recent_reviews ?? [] as $review)
                        <tr>
                            <td><strong>{{ $review->employee_name ?? 'N/A' }}</strong></td>
                            <td>{{ $review->cycle_name ?? '—' }}</td>
                            <td>
                                <span class="hims-badge {{ $review->status === 'approved' ? 'green' : ($review->status === 'draft' ? 'gray' : 'yellow') }}">
                                    {{ ucfirst(str_replace('_',' ',$review->status ?? 'draft')) }}
                                </span>
                            </td>
                            <td>{{ $review->overall_score ? number_format($review->overall_score,2).'/5.00' : '—' }}</td>
                            <td><a href="{{ route('performance.show', $review->review_id ?? 0) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No reviews yet. <a href="{{ route('performance.index') }}" class="text-primary-hims">Create one</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="hims-card animate-in mb-3" style="animation-delay:.3s">
            <div class="card-header"><h5>🚀 Quick Actions</h5></div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('performance.cycles.create') }}" class="btn-hims btn-hims-primary" style="justify-content:center"><i class="bi bi-plus-circle"></i> New Review Cycle</a>
                <a href="{{ route('training.sessions.create') }}" class="btn-hims btn-hims-outline" style="justify-content:center"><i class="bi bi-calendar-plus"></i> Schedule Training</a>
                <a href="{{ route('recognition.posts.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-star"></i> Give Recognition</a>
                <a href="{{ route('learning.courses.create') }}" class="btn-hims btn-hims-ghost" style="justify-content:center"><i class="bi bi-book"></i> Create Course</a>
            </div>
        </div>
        <div class="hims-card animate-in" style="animation-delay:.36s">
            <div class="card-header"><h5>🤖 Gemini AI Assistant</h5></div>
            <div class="card-body">
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Ask in English or Tagalog/Taglish</p>
                <form action="{{ route('ai.query') }}" method="POST">
                    @csrf
                    <textarea name="query" class="hims-input" rows="3" placeholder="Sino ang successor para sa ICU Head Nurse?"></textarea>
                    <button type="submit" class="btn-hims btn-hims-primary mt-2" style="width:100%;justify-content:center">
                        <i class="bi bi-send"></i> Ask Gemini AI
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="hims-card animate-in" style="animation-delay:.42s">
            <div class="card-header">
                <h5>⚠️ Succession Risk Positions</h5>
                <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Position</th><th>Department</th><th>Risk</th></tr></thead>
                    <tbody>
                        @forelse($risk_positions ?? [] as $pos)
                        <tr>
                            <td><strong>{{ $pos->position_title }}</strong></td>
                            <td>{{ $pos->department_name ?? '—' }}</td>
                            <td>
                                <span class="hims-badge {{ $pos->vacancy_risk === 'critical' ? 'red' : ($pos->vacancy_risk === 'high' ? 'yellow' : 'green') }}">
                                    {{ ucfirst($pos->vacancy_risk) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="color:#9ca3af;padding:24px">No critical positions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="hims-card animate-in" style="animation-delay:.48s">
            <div class="card-header">
                <h5>❤️ Latest Recognition</h5>
                <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost btn-sm">View Wall</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($recent_recognition ?? [] as $post)
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <div style="width:36px;height:36px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">⭐</div>
                    <div>
                        <div style="font-size:13px;font-weight:600">{{ $post->author_name ?? 'Someone' }} → {{ $post->recipient_name ?? 'Someone' }}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ Str::limit($post->message ?? '', 80) }}</div>
                        <span class="recognition-badge-pill mt-1">{{ $post->badge_name ?? '🏅 Badge' }}</span>
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No recognition posts yet. <a href="{{ route('recognition.posts.create') }}" class="text-primary-hims">Be the first!</a></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
