@extends('layouts.hims')
@section('title','Social Recognition')
@section('page-title','Social Recognition')
@section('breadcrumb','HIMS / Recognition')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4" style="flex-wrap:wrap">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Recognition Wall</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Peer and supervisor appreciation tied to hospital values.</p>
    </div>
    <div class="d-flex gap-2" style="flex-wrap:wrap">
        @can('manage-recognition')
        <button type="button" class="btn-hims btn-hims-outline" data-modal-open="recognitionBadgeModal">
            <i class="bi bi-patch-plus"></i> New Value Badge
        </button>
        @endcan
        @if($currentEmployeeId)
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="recognitionPostModal">
            <i class="bi bi-stars"></i> Give Recognition
        </button>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3" data-auto-dismiss><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
@endif
@if(! $currentEmployeeId)
    <div class="hims-alert warning mb-3"><i class="bi bi-link-45deg"></i> Link this account to an employee profile to post, react, or comment.</div>
@endif

<div class="hims-tabs" aria-label="Recognition views">
    <a href="{{ route('recognition.index') }}" class="hims-tab {{ $view === 'feed' ? 'active' : '' }}">
        <i class="bi bi-activity"></i> Wall
    </a>
    <a href="{{ route('recognition.index', ['view' => 'received']) }}" class="hims-tab {{ $view === 'received' ? 'active' : '' }}">
        <i class="bi bi-inbox"></i> Received
    </a>
    <a href="{{ route('recognition.index', ['view' => 'sent']) }}" class="hims-tab {{ $view === 'sent' ? 'active' : '' }}">
        <i class="bi bi-send"></i> Sent
    </a>
    @if($canModerate)
    <a href="{{ route('recognition.index', ['view' => 'private']) }}" class="hims-tab {{ $view === 'private' ? 'active' : '' }}">
        <i class="bi bi-lock"></i> Private
    </a>
    @endif
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-stars"></i></div><div class="stat-value">{{ $stats['total_posts'] }}</div><div class="stat-label">Approved Recognition</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-patch-check"></i></div><div class="stat-value">{{ $stats['badges_given'] }}</div><div class="stat-label">Value Badges Given</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-hand-thumbs-up"></i></div><div class="stat-value">{{ $stats['total_reactions'] }}</div><div class="stat-label">Reactions</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-building-check"></i></div><div class="stat-value" style="font-size:18px">{{ $stats['top_dept'] }}</div><div class="stat-label">Top Department This Month</div></div></div>
</div>

<div class="row g-3 align-items-start">
    <div class="col-xl-8">
        <div class="hims-card">
            <div class="card-header">
                <h5>
                    <i class="bi {{ $view === 'sent' ? 'bi-send' : ($view === 'received' ? 'bi-inbox' : ($view === 'private' ? 'bi-lock' : 'bi-activity')) }}"></i>
                    {{ $view === 'sent' ? 'Recognition You Sent' : ($view === 'received' ? 'Recognition You Received' : ($view === 'private' ? 'Private Recognition' : 'Recent Recognition')) }}
                </h5>
                <span style="font-size:12px;color:#6b7280">{{ $posts->total() }} post{{ $posts->total() === 1 ? '' : 's' }}</span>
            </div>
            <div class="card-body" style="padding:0">
                @forelse($posts as $post)
                @php($postComments = $comments->get($post->post_id, collect()))
                <article id="recognition-post-{{ $post->post_id }}" style="padding:20px;border-bottom:1px solid var(--hims-border);scroll-margin-top:84px">
                    <div class="d-flex justify-content-between align-items-start gap-3" style="flex-wrap:wrap">
                        <div style="min-width:0">
                            <div style="font-size:13.5px;color:#4b5563;line-height:1.5">
                                <strong style="color:var(--hims-text-dark)">{{ $post->author_first_name }} {{ $post->author_last_name }}</strong>
                                recognized
                                <strong style="color:var(--hims-primary-dark)">{{ $post->recipient_first_name }} {{ $post->recipient_last_name }}</strong>
                            </div>
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:2px">
                                {{ $post->recipient_department }} &middot; {{ \Carbon\Carbon::parse($post->created_at)->diffForHumans() }}
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center" style="flex-wrap:wrap">
                            <span class="hims-badge {{ $post->post_type === 'supervisor' ? 'blue' : 'gray' }}">
                                <i class="bi {{ $post->post_type === 'supervisor' ? 'bi-person-check' : 'bi-people' }}"></i>
                                {{ $post->post_type === 'supervisor' ? 'Supervisor' : 'Peer' }}
                            </span>
                            @if(! $post->is_public)<span class="hims-badge gray"><i class="bi bi-lock"></i> Private</span>@endif
                            @if($post->is_featured)<span class="hims-badge yellow"><i class="bi bi-pin-angle-fill"></i> Featured</span>@endif
                        </div>
                    </div>

                    @if($post->badge_name)
                    <div class="d-flex align-items-center gap-2" style="margin-top:15px">
                        <span style="width:34px;height:34px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;background:{{ $post->badge_color }}18;color:{{ $post->badge_color }};border:1px solid {{ $post->badge_color }}55;flex:0 0 auto">
                            <i class="{{ $post->badge_icon ?: 'bi bi-award' }}"></i>
                        </span>
                        <div>
                            <div style="font-size:13px;font-weight:700">{{ $post->badge_name }}</div>
                            @if($post->hospital_value)<div style="font-size:11px;color:#6b7280">{{ $post->hospital_value }}</div>@endif
                        </div>
                    </div>
                    @endif

                    <p style="font-size:14px;line-height:1.65;color:#374151;margin:15px 0 0;white-space:pre-line">{{ $post->message }}</p>

                    <div class="d-flex align-items-center gap-2" style="margin-top:16px;flex-wrap:wrap">
                        @if($currentEmployeeId)
                        <form method="POST" action="{{ route('recognition.react', $post->post_id) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="reaction_type" value="clap">
                            <button type="submit" class="btn-hims {{ $post->user_reacted ? 'btn-hims-primary' : 'btn-hims-ghost' }} btn-sm" aria-label="Toggle applause reaction">
                                <i class="bi bi-hand-thumbs-up{{ $post->user_reacted ? '-fill' : '' }}"></i> {{ $post->reactions_count }}
                            </button>
                        </form>
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-comment-toggle="comment-{{ $post->post_id }}">
                            <i class="bi bi-chat"></i> {{ $post->comments_count }}
                        </button>
                        @endif

                        @if($canModerate)
                        <details style="margin-left:auto">
                            <summary class="btn-hims btn-hims-ghost btn-sm" style="list-style:none;cursor:pointer"><i class="bi bi-shield-check"></i> Moderate</summary>
                            <form method="POST" action="{{ route('recognition.posts.moderate', $post->post_id) }}" class="d-flex gap-2" style="margin-top:8px;max-width:620px;flex-wrap:wrap">
                                @csrf @method('PATCH')
                                <select name="moderation_status" class="hims-input hims-select" style="width:135px" aria-label="Post moderation status">
                                    <option value="approved">Approved</option>
                                    <option value="flagged">Flagged</option>
                                    <option value="removed">Removed</option>
                                </select>
                                <input name="moderation_note" class="hims-input" maxlength="1000" placeholder="Moderation note" style="flex:1;min-width:180px">
                                <button type="submit" class="btn-hims btn-hims-outline btn-sm"><i class="bi bi-check2"></i> Save</button>
                            </form>
                        </details>
                        @endif
                    </div>

                    <div id="comment-{{ $post->post_id }}" style="margin-top:14px;{{ $postComments->isEmpty() ? 'display:none' : '' }}">
                        @foreach($postComments as $comment)
                        <div style="display:flex;gap:10px;padding:10px 0;border-top:1px solid #eef2f7">
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--hims-primary-pale);display:flex;align-items:center;justify-content:center;color:var(--hims-primary);flex:0 0 auto"><i class="bi bi-person"></i></div>
                            <div style="flex:1;min-width:0">
                                <div class="d-flex align-items-center gap-2" style="flex-wrap:wrap">
                                    <strong style="font-size:12.5px">{{ $comment->author_first_name }} {{ $comment->author_last_name }}</strong>
                                    <span style="font-size:10.5px;color:#9ca3af">{{ \Carbon\Carbon::parse($comment->created_at)->diffForHumans() }}</span>
                                    @if($comment->moderation_status !== 'approved')
                                    <span class="hims-badge {{ $comment->moderation_status === 'removed' ? 'red' : 'yellow' }}">{{ ucfirst($comment->moderation_status) }}</span>
                                    @endif
                                </div>
                                <div style="font-size:12.5px;color:#4b5563;margin-top:3px;line-height:1.5">{{ $comment->comment_text }}</div>
                                @if($canModerate)
                                <form method="POST" action="{{ route('recognition.comments.moderate', $comment->comment_id) }}" class="d-flex gap-2" style="margin-top:7px;flex-wrap:wrap">
                                    @csrf @method('PATCH')
                                    <select name="moderation_status" class="hims-input hims-select" style="width:125px;padding:5px 8px;font-size:11.5px" aria-label="Comment moderation status">
                                        <option value="approved" @selected($comment->moderation_status === 'approved')>Approved</option>
                                        <option value="flagged" @selected($comment->moderation_status === 'flagged')>Flagged</option>
                                        <option value="removed" @selected($comment->moderation_status === 'removed')>Removed</option>
                                    </select>
                                    <button type="submit" class="btn-hims btn-hims-ghost btn-sm"><i class="bi bi-check2"></i></button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @endforeach

                        @if($currentEmployeeId)
                        <form method="POST" action="{{ route('recognition.comments.store', $post->post_id) }}" class="d-flex gap-2" style="padding-top:10px;border-top:1px solid #eef2f7">
                            @csrf
                            <input type="text" name="comment_text" class="hims-input" required maxlength="500" placeholder="Add a comment" aria-label="Comment">
                            <button type="submit" class="btn-hims btn-hims-primary btn-sm" title="Post comment"><i class="bi bi-send"></i></button>
                        </form>
                        @endif
                    </div>
                </article>
                @empty
                <div style="padding:54px 24px;text-align:center;color:#6b7280">
                    <i class="bi bi-stars" style="font-size:34px;color:#9ca3af"></i>
                    <div style="font-weight:700;color:var(--hims-text-dark);margin-top:10px">No recognition here yet</div>
                </div>
                @endforelse
            </div>
        </div>

        @if($posts->hasPages())
        <div style="margin-top:16px">{{ $posts->links() }}</div>
        @endif
    </div>

    <div class="col-xl-4 d-flex flex-column gap-3">
        @if($canModerate && ($moderationPosts->isNotEmpty() || $moderationComments->isNotEmpty()))
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-shield-exclamation"></i> Moderation Queue</h5></div>
            <div class="card-body d-flex flex-column gap-3">
                @foreach($moderationPosts as $item)
                <div style="padding-bottom:12px;border-bottom:1px solid var(--hims-border)">
                    <div class="d-flex justify-content-between gap-2">
                        <strong style="font-size:12.5px">{{ $item->author_first_name }} {{ $item->author_last_name }}</strong>
                        <span class="hims-badge {{ $item->moderation_status === 'removed' ? 'red' : 'yellow' }}">{{ ucfirst($item->moderation_status) }}</span>
                    </div>
                    <div style="font-size:12px;color:#4b5563;margin-top:5px">{{ \Illuminate\Support\Str::limit($item->message, 130) }}</div>
                    <form method="POST" action="{{ route('recognition.posts.moderate', $item->post_id) }}" class="d-flex gap-2" style="margin-top:8px;flex-wrap:wrap">
                        @csrf @method('PATCH')
                        <select name="moderation_status" class="hims-input hims-select" style="width:125px;padding:5px 8px;font-size:11.5px">
                            <option value="approved">Approve</option><option value="flagged">Flag</option><option value="removed">Remove</option>
                        </select>
                        <button type="submit" class="btn-hims btn-hims-outline btn-sm"><i class="bi bi-check2"></i> Update</button>
                    </form>
                </div>
                @endforeach
                @foreach($moderationComments as $item)
                <div style="padding-bottom:12px;border-bottom:1px solid var(--hims-border)">
                    <div class="d-flex justify-content-between gap-2">
                        <strong style="font-size:12.5px">Comment by {{ $item->author_first_name }} {{ $item->author_last_name }}</strong>
                        <span class="hims-badge {{ $item->moderation_status === 'removed' ? 'red' : 'yellow' }}">{{ ucfirst($item->moderation_status) }}</span>
                    </div>
                    <div style="font-size:12px;color:#4b5563;margin-top:5px">{{ \Illuminate\Support\Str::limit($item->comment_text, 130) }}</div>
                    <form method="POST" action="{{ route('recognition.comments.moderate', $item->comment_id) }}" class="d-flex gap-2" style="margin-top:8px;flex-wrap:wrap">
                        @csrf @method('PATCH')
                        <select name="moderation_status" class="hims-input hims-select" style="width:125px;padding:5px 8px;font-size:11.5px">
                            <option value="approved">Approve</option><option value="flagged">Flag</option><option value="removed">Remove</option>
                        </select>
                        <button type="submit" class="btn-hims btn-hims-outline btn-sm"><i class="bi bi-check2"></i> Update</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-patch-check"></i> Hospital Values</h5>
                @can('manage-recognition')
                <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-modal-open="recognitionBadgeModal" title="Create value badge"><i class="bi bi-plus-lg"></i></button>
                @endcan
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @foreach($badges as $badge)
                <div class="d-flex align-items-center gap-3" style="padding:10px;border:1px solid var(--hims-border);border-radius:7px">
                    <span style="width:34px;height:34px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;background:{{ $badge->badge_color }}18;color:{{ $badge->badge_color }};border:1px solid {{ $badge->badge_color }}55;flex:0 0 auto"><i class="{{ $badge->badge_icon ?: 'bi bi-award' }}"></i></span>
                    <div style="min-width:0;flex:1">
                        <div style="font-size:12.5px;font-weight:700">{{ $badge->badge_name }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $badge->hospital_value ?: 'Hospital value' }} &middot; {{ $badge->points_value }} pts</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@if($currentEmployeeId)
    @include('recognition._post-modal')
@endif
@can('manage-recognition')
    @include('recognition._badge-modal')
@endcan
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-comment-toggle]');
    if (!button) return;
    const thread = document.getElementById(button.dataset.commentToggle);
    if (thread) thread.style.display = thread.style.display === 'none' ? 'block' : 'none';
});
</script>
@endpush
