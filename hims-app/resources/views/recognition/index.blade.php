@extends('layouts.hims')
@section('title','Social Recognition')
@section('page-title','Social Recognition')
@section('breadcrumb','HIMS / Recognition')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Recognition Wall</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Celebrate excellence, share kudos, and build a culture of appreciation.</p>
    </div>
    <a href="{{ route('recognition.posts.create') }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-star-fill"></i> Give Recognition
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value">{{ $stats['total_posts'] ?? 0 }}</div><div class="stat-label">Total Recognitions</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🏅</div><div class="stat-value">{{ $stats['badges_given'] ?? 0 }}</div><div class="stat-label">Badges Awarded</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">👍</div><div class="stat-value">{{ $stats['total_reactions'] ?? 0 }}</div><div class="stat-label">Total Reactions</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value">{{ $stats['top_dept'] ?? '—' }}</div><div class="stat-label">Top Department</div></div></div>
</div>

<div class="row g-3">
    <!-- Recognition Feed -->
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-layout-text-sidebar-reverse"></i> Recognition Feed</h5>
                <div class="d-flex gap-2">
                    <select class="hims-input hims-select" style="width:130px;padding:6px 12px;font-size:13px" id="feedFilter">
                        <option value="all">All Posts</option>
                        <option value="peer">Peer-to-Peer</option>
                        <option value="management">Management</option>
                        <option value="milestone">Milestone</option>
                    </select>
                </div>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($posts ?? [] as $post)
                <div class="recognition-post">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:40px;height:40px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--hims-primary-dark)">
                                {{ strtoupper(substr($post->author_name ?? 'A',0,1)) }}
                            </div>
                            <div>
                                <div style="font-size:13.5px;font-weight:700">{{ $post->author_name ?? 'Anonymous' }}</div>
                                <div style="font-size:11.5px;color:#9ca3af">{{ $post->author_dept ?? '' }} · {{ \Carbon\Carbon::parse($post->created_at)->diffForHumans() }}</div>
                            </div>
                        </div>
                        @if($post->badge_name)
                        <span class="recognition-badge-pill">
                            {{ $post->badge_icon ?? '🏅' }} {{ $post->badge_name }}
                        </span>
                        @endif
                    </div>

                    <div style="font-size:13px;margin-bottom:12px;line-height:1.7;color:var(--hims-text-dark)">
                        <span style="color:var(--hims-primary);font-weight:700">@{{ $post->recipient_name ?? 'Someone' }}</span>
                        {{ $post->message }}
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;padding-top:10px;border-top:1px solid var(--hims-border)">
                        <form method="POST" action="{{ route('recognition.react', $post->post_id) }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="reaction_type" value="like">
                            <button type="submit" style="border:none;background:none;cursor:pointer;display:flex;align-items:center;gap:4px;color:{{ ($post->user_reacted ?? false) ? 'var(--hims-primary)' : '#9ca3af' }};font-size:13px;font-weight:{{ ($post->user_reacted ?? false) ? '700' : '400' }}">
                                <i class="bi bi-heart-fill"></i> {{ $post->reactions_count ?? 0 }}
                            </button>
                        </form>
                        <button style="border:none;background:none;cursor:pointer;display:flex;align-items:center;gap:4px;color:#9ca3af;font-size:13px" onclick="toggleComment('comment-{{ $post->post_id }}')">
                            <i class="bi bi-chat"></i> {{ $post->comments_count ?? 0 }}
                        </button>
                        @if($post->is_featured)
                        <span class="hims-badge yellow" style="margin-left:auto">⭐ Featured</span>
                        @endif
                    </div>

                    <div id="comment-{{ $post->post_id }}" style="display:none;margin-top:12px">
                        <form method="POST" action="{{ route('recognition.comments.store', $post->post_id) }}">
                            @csrf
                            <div style="display:flex;gap:8px">
                                <input type="text" name="comment_text" class="hims-input" placeholder="Write a comment..." style="font-size:13px">
                                <button type="submit" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-send"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:60px 20px;color:#9ca3af">
                    <div style="font-size:48px;margin-bottom:12px">⭐</div>
                    <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No recognitions yet</div>
                    <div style="font-size:13px;margin-bottom:16px">Be the first to celebrate a colleague!</div>
                    <a href="{{ route('recognition.posts.create') }}" class="btn-hims btn-hims-primary">Give Recognition</a>
                </div>
                @endforelse

                @if(isset($posts) && $posts->hasPages())
                <div style="margin-top:8px">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar: Leaderboard + Badges -->
    <div class="col-lg-4">
        <!-- Leaderboard -->
        <div class="hims-card mb-3">
            <div class="card-header">
                <h5><i class="bi bi-trophy-fill"></i> Monthly Leaderboard</h5>
                <select class="hims-input hims-select" style="width:110px;padding:5px 10px;font-size:12px">
                    <option>This Month</option>
                    <option>Last Month</option>
                    <option>This Year</option>
                </select>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @forelse($leaderboard ?? [] as $i => $entry)
                <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;background:{{ $i === 0 ? 'linear-gradient(90deg,#fef3c7,#fde68a33)' : 'var(--hims-primary-pale)' }};border:1px solid {{ $i === 0 ? '#fcd34d' : 'var(--hims-border)' }}">
                    <div style="width:28px;text-align:center;font-size:18px">{{ ['🥇','🥈','🥉'][$i] ?? '#'.($i+1) }}</div>
                    <div style="flex:1">
                        <div style="font-weight:700;font-size:13px">{{ $entry->employee_name }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $entry->department_name }}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-weight:800;color:var(--hims-primary);font-size:14px">{{ $entry->total_points }}</div>
                        <div style="font-size:10px;color:#9ca3af">pts</div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No data yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Badge Library -->
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-collection-fill"></i> Badge Library</h5>
                <a href="{{ route('recognition.badges.create') }}" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-plus"></i></a>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @forelse($badges ?? [] as $badge)
                    <div style="padding:10px;border:1px solid var(--hims-border);border-radius:8px;text-align:center;background:var(--hims-primary-pale);transition:.2s" onmouseover="this.style.borderColor='var(--hims-primary)'" onmouseout="this.style.borderColor='var(--hims-border)'">
                        <div style="font-size:24px">{{ $badge->badge_icon ?? '🏅' }}</div>
                        <div style="font-size:11.5px;font-weight:600;margin-top:4px">{{ $badge->badge_name }}</div>
                        <div style="font-size:10px;color:#9ca3af">+{{ $badge->points_value }} pts</div>
                    </div>
                    @empty
                    <div style="grid-column:span 2;text-align:center;color:#9ca3af;padding:20px">No badges yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
function toggleComment(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush
