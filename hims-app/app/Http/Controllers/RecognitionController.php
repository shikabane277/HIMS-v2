<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecognitionController extends Controller
{
    public function index(Request $request)
    {
        $currentEmployeeId = $this->currentEmployeeId();
        $view = in_array($request->query('view'), ['feed', 'sent', 'received', 'private'], true)
            ? $request->query('view')
            : 'feed';
        $canModerate = auth()->user()->hasRole('admin', 'hr_manager');
        $focusPostId = $request->query('focus');

        // Notification and global-search links carry only a post id. Resolve the
        // correct visible tab here so a private item opens Sent/Received for its
        // participants and Private for moderators without widening its audience.
        if ($focusPostId) {
            $focusedPost = DB::table('recognition_posts')
                ->where('post_id', $focusPostId)
                ->where('moderation_status', 'approved')
                ->first(['author_id', 'recipient_id', 'is_public']);

            if (! $focusedPost) {
                $focusPostId = null;
            } elseif ($focusedPost->is_public) {
                $view = 'feed';
            } elseif ($canModerate) {
                $view = 'private';
            } elseif ($currentEmployeeId === $focusedPost->author_id) {
                $view = 'sent';
            } elseif ($currentEmployeeId === $focusedPost->recipient_id) {
                $view = 'received';
            } else {
                $focusPostId = null;
            }
        }

        if ($view === 'private' && ! $canModerate) {
            $view = 'feed';
        }

        $stats = [
            'total_posts' => DB::table('recognition_posts')
                ->where('moderation_status', 'approved')->where('is_public', true)->count(),
            'badges_given' => DB::table('recognition_posts')
                ->where('moderation_status', 'approved')->where('is_public', true)->whereNotNull('badge_id')->count(),
            'total_reactions' => DB::table('recognition_reactions as rr')
                ->join('recognition_posts as rp', 'rr.post_id', '=', 'rp.post_id')
                ->where('rp.moderation_status', 'approved')->where('rp.is_public', true)->count(),
            'top_dept' => $this->topDepartmentThisMonth(),
        ];

        $postsQuery = DB::table('recognition_posts as rp')
            ->join('employees as author', 'rp.author_id', '=', 'author.employee_id')
            ->join('employees as recipient', 'rp.recipient_id', '=', 'recipient.employee_id')
            ->join('departments as department', 'recipient.department_id', '=', 'department.department_id')
            ->leftJoin('recognition_badges as badge', 'rp.badge_id', '=', 'badge.badge_id')
            ->where('rp.moderation_status', 'approved')
            ->select(
                'rp.post_id', 'rp.author_id', 'rp.recipient_id', 'rp.message', 'rp.post_type',
                'rp.is_public', 'rp.is_featured', 'rp.created_at',
                'author.first_name as author_first_name', 'author.last_name as author_last_name',
                'recipient.first_name as recipient_first_name', 'recipient.last_name as recipient_last_name',
                'department.name as recipient_department',
                'badge.badge_name', 'badge.badge_icon', 'badge.badge_color', 'badge.hospital_value',
            )
            ->selectSub(function ($query) {
                $query->from('recognition_reactions as reaction_count')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reaction_count.post_id', 'rp.post_id');
            }, 'reactions_count')
            ->selectSub(function ($query) {
                $query->from('recognition_comments as comment_count')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('comment_count.post_id', 'rp.post_id')
                    ->where('comment_count.moderation_status', 'approved');
            }, 'comments_count');

        if ($currentEmployeeId) {
            $postsQuery->selectSub(function ($query) use ($currentEmployeeId) {
                $query->from('recognition_reactions as own_reaction')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('own_reaction.post_id', 'rp.post_id')
                    ->where('own_reaction.employee_id', $currentEmployeeId);
            }, 'user_reacted');
        } else {
            $postsQuery->selectRaw('0 as user_reacted');
        }

        if ($view === 'sent') {
            $currentEmployeeId
                ? $postsQuery->where('rp.author_id', $currentEmployeeId)
                : $postsQuery->whereRaw('1 = 0');
        } elseif ($view === 'received') {
            $currentEmployeeId
                ? $postsQuery->where('rp.recipient_id', $currentEmployeeId)
                : $postsQuery->whereRaw('1 = 0');
        } elseif ($view === 'private') {
            $postsQuery->where('rp.is_public', false);
        } else {
            $postsQuery->where('rp.is_public', true);
        }

        if ($focusPostId) {
            $postsQuery->where('rp.post_id', $focusPostId);
        }

        $posts = $postsQuery
            ->orderByDesc('rp.is_featured')
            ->orderByDesc('rp.created_at')
            ->paginate(10)
            ->withQueryString();

        $postIds = $posts->getCollection()->pluck('post_id')->all();
        $comments = collect();

        if ($postIds) {
            $comments = DB::table('recognition_comments as comment')
                ->join('employees as author', 'comment.author_id', '=', 'author.employee_id')
                ->whereIn('comment.post_id', $postIds)
                ->when(! $canModerate, fn ($query) => $query->where('comment.moderation_status', 'approved'))
                ->select(
                    'comment.comment_id', 'comment.post_id', 'comment.author_id', 'comment.comment_text',
                    'comment.moderation_status', 'comment.created_at',
                    'author.first_name as author_first_name', 'author.last_name as author_last_name',
                )
                ->orderBy('comment.created_at')
                ->get()
                ->groupBy('post_id');
        }

        $employees = DB::table('employees')
            ->where('employment_status', 'active')
            ->when($currentEmployeeId, fn ($query, $id) => $query->where('employee_id', '!=', $id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['employee_id', 'first_name', 'last_name', 'position_title']);

        $badges = DB::table('recognition_badges')
            ->where('is_active', true)
            ->orderBy('hospital_value')
            ->orderBy('badge_name')
            ->get();

        $moderationPosts = collect();
        $moderationComments = collect();

        if ($canModerate) {
            $moderationPosts = DB::table('recognition_posts as rp')
                ->join('employees as author', 'rp.author_id', '=', 'author.employee_id')
                ->join('employees as recipient', 'rp.recipient_id', '=', 'recipient.employee_id')
                ->where('rp.moderation_status', '!=', 'approved')
                ->select(
                    'rp.post_id', 'rp.message', 'rp.moderation_status', 'rp.moderation_note', 'rp.created_at',
                    'author.first_name as author_first_name', 'author.last_name as author_last_name',
                    'recipient.first_name as recipient_first_name', 'recipient.last_name as recipient_last_name',
                )
                ->orderByDesc('rp.updated_at')
                ->limit(12)
                ->get();

            $moderationComments = DB::table('recognition_comments as comment')
                ->join('employees as author', 'comment.author_id', '=', 'author.employee_id')
                ->where('comment.moderation_status', '!=', 'approved')
                ->select(
                    'comment.comment_id', 'comment.post_id', 'comment.comment_text',
                    'comment.moderation_status', 'comment.created_at',
                    'author.first_name as author_first_name', 'author.last_name as author_last_name',
                )
                ->orderByDesc('comment.updated_at')
                ->limit(12)
                ->get();
        }

        return view('recognition.index', compact(
            'stats', 'posts', 'comments', 'employees', 'badges', 'view', 'currentEmployeeId',
            'canModerate', 'moderationPosts', 'moderationComments',
        ));
    }

    public function storePost(Request $request, NotificationService $notifications)
    {
        $authorId = $this->currentEmployeeId();

        if (! $authorId) {
            return back()->withInput()->with('error', 'Your account is not linked to an employee profile, so it cannot post recognition.');
        }

        $validated = $request->validate([
            'recipient_id' => ['required', 'string', Rule::exists('employees', 'employee_id')->where('employment_status', 'active')],
            'badge_id' => ['nullable', 'string', Rule::exists('recognition_badges', 'badge_id')->where('is_active', true)],
            'message' => ['required', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        if ($validated['recipient_id'] === $authorId) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Recognition must be for a colleague, not yourself.',
            ]);
        }

        $recipient = DB::table('employees')
            ->where('employee_id', $validated['recipient_id'])
            ->first(['employee_id', 'first_name', 'last_name', 'supervisor_id']);
        $authorName = $this->employeeName($authorId);
        $postId = (string) Str::uuid();
        $postType = $recipient->supervisor_id === $authorId ? 'supervisor' : 'peer';
        $isPublic = ! array_key_exists('is_public', $validated) || (bool) $validated['is_public'];

        DB::transaction(function () use ($validated, $authorId, $authorName, $postId, $postType, $recipient, $notifications, $isPublic) {
            DB::table('recognition_posts')->insert([
                'post_id' => $postId,
                'author_id' => $authorId,
                'recipient_id' => $recipient->employee_id,
                'badge_id' => $validated['badge_id'] ?? null,
                'post_type' => $postType,
                'message' => $validated['message'],
                'is_public' => $isPublic,
                'is_featured' => false,
                'moderation_status' => 'approved',
                'link_to_review_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $notifications->notify(
                $recipient->employee_id,
                'recognition_received',
                $authorName.' recognized your work',
                Str::limit($validated['message'], 240),
                'recognition_post',
                $postId,
            );
        });

        AuditTrail::record('recognition_created', 'recognition_posts', $postId, afterState: [
            'author_id' => $authorId,
            'recipient_id' => $recipient->employee_id,
            'is_public' => $isPublic,
            'post_type' => $postType,
        ]);

        return redirect()->route('recognition.index')->with('success', $isPublic ? 'Recognition posted.' : 'Private recognition sent.');
    }

    public function react(Request $request, string $postId, NotificationService $notifications)
    {
        $employeeId = $this->currentEmployeeId();

        if (! $employeeId) {
            return back()->with('error', 'Your account is not linked to an employee profile.');
        }

        $validated = $request->validate([
            'reaction_type' => ['nullable', Rule::in(['like', 'clap', 'heart', 'celebrate', 'support'])],
        ]);
        $post = $this->visiblePost($postId);
        $existing = DB::table('recognition_reactions')
            ->where('post_id', $postId)
            ->where('employee_id', $employeeId)
            ->first();

        if ($existing) {
            DB::table('recognition_reactions')->where('reaction_id', $existing->reaction_id)->delete();
        } else {
            DB::table('recognition_reactions')->insert([
                'reaction_id' => (string) Str::uuid(),
                'post_id' => $postId,
                'employee_id' => $employeeId,
                'reaction_type' => $validated['reaction_type'] ?? 'clap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($post->author_id !== $employeeId) {
                $notifications->notify(
                    $post->author_id,
                    'recognition_reaction',
                    $this->employeeName($employeeId).' reacted to your recognition post',
                    null,
                    'recognition_post',
                    $postId,
                );
            }
        }

        return back();
    }

    public function storeComment(Request $request, string $postId, NotificationService $notifications)
    {
        $authorId = $this->currentEmployeeId();

        if (! $authorId) {
            return back()->with('error', 'Your account is not linked to an employee profile, so it cannot comment.');
        }

        $validated = $request->validate(['comment_text' => ['required', 'string', 'max:500']]);
        $post = $this->visiblePost($postId);
        $commentId = (string) Str::uuid();

        DB::table('recognition_comments')->insert([
            'comment_id' => $commentId,
            'post_id' => $postId,
            'author_id' => $authorId,
            'comment_text' => $validated['comment_text'],
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipients = array_values(array_filter(
            array_unique([$post->author_id, $post->recipient_id]),
            fn ($recipientId) => $recipientId !== $authorId,
        ));

        $notifications->notifyMany(
            $recipients,
            'recognition_comment',
            $this->employeeName($authorId).' commented on a recognition post',
            Str::limit($validated['comment_text'], 240),
            'recognition_post',
            $postId,
        );

        return back()->with('success', 'Comment added.');
    }

    public function storeBadge(Request $request)
    {
        $validated = $request->validate([
            'badge_name' => ['required', 'string', 'max:100', 'unique:recognition_badges,badge_name'],
            'badge_icon' => ['nullable', 'string', 'max:50'],
            'badge_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hospital_value' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'points_value' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $badgeId = (string) Str::uuid();

        DB::table('recognition_badges')->insert([
            'badge_id' => $badgeId,
            'badge_name' => $validated['badge_name'],
            'badge_icon' => $validated['badge_icon'] ?? 'bi bi-award',
            'badge_color' => $validated['badge_color'] ?? '#047857',
            'hospital_value' => $validated['hospital_value'] ?? null,
            'description' => $validated['description'] ?? null,
            'points_value' => $validated['points_value'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::record('create_recognition_badge', 'recognition_badges', $badgeId, afterState: $validated);

        return redirect()->route('recognition.index')->with('success', 'Value badge created.');
    }

    public function moderatePost(Request $request, string $postId, NotificationService $notifications)
    {
        $validated = $request->validate([
            'moderation_status' => ['required', Rule::in(['approved', 'flagged', 'removed'])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $post = DB::table('recognition_posts')->where('post_id', $postId)->first();
        abort_if(! $post, 404);

        DB::table('recognition_posts')->where('post_id', $postId)->update([
            'moderation_status' => $validated['moderation_status'],
            'moderated_by' => $this->currentEmployeeId(),
            'moderation_note' => $validated['moderation_note'] ?? null,
            'updated_at' => now(),
        ]);

        AuditTrail::record(
            'recognition_moderated',
            'recognition_posts',
            $postId,
            beforeState: ['moderation_status' => $post->moderation_status, 'moderation_note' => $post->moderation_note],
            afterState: $validated,
        );

        if ($post->author_id !== $this->currentEmployeeId()) {
            $notifications->notify(
                $post->author_id,
                'recognition_moderated',
                'A recognition post you wrote was '.$validated['moderation_status'],
                $validated['moderation_note'] ?? null,
                'recognition_post',
                $postId,
            );
        }

        return back()->with('success', 'Recognition moderation updated.');
    }

    public function moderateComment(Request $request, string $commentId, NotificationService $notifications)
    {
        $validated = $request->validate([
            'moderation_status' => ['required', Rule::in(['approved', 'flagged', 'removed'])],
        ]);
        $comment = DB::table('recognition_comments')->where('comment_id', $commentId)->first();
        abort_if(! $comment, 404);

        DB::table('recognition_comments')->where('comment_id', $commentId)->update([
            'moderation_status' => $validated['moderation_status'],
            'updated_at' => now(),
        ]);

        AuditTrail::record(
            'recognition_moderated',
            'recognition_comments',
            $commentId,
            beforeState: ['moderation_status' => $comment->moderation_status],
            afterState: $validated,
        );

        if ($comment->author_id !== $this->currentEmployeeId()) {
            $notifications->notify(
                $comment->author_id,
                'recognition_moderated',
                'A recognition comment you wrote was '.$validated['moderation_status'],
                null,
                'recognition_post',
                $comment->post_id,
            );
        }

        return back()->with('success', 'Comment moderation updated.');
    }

    private function visiblePost(string $postId): object
    {
        $post = DB::table('recognition_posts')
            ->where('post_id', $postId)
            ->where('moderation_status', 'approved')
            ->first(['post_id', 'author_id', 'recipient_id', 'is_public']);

        abort_if(! $post, 404);

        $employeeId = $this->currentEmployeeId();
        $isParticipant = $employeeId && in_array($employeeId, [$post->author_id, $post->recipient_id], true);
        $isModerator = auth()->user()?->hasRole('admin', 'hr_manager') ?? false;

        abort_unless((bool) $post->is_public || $isParticipant || $isModerator, 404);

        return $post;
    }

    private function employeeName(string $employeeId): string
    {
        $employee = DB::table('employees')->where('employee_id', $employeeId)
            ->first(['first_name', 'last_name']);

        return $employee ? trim($employee->first_name.' '.$employee->last_name) : 'A colleague';
    }

    private function topDepartmentThisMonth(): string
    {
        return DB::table('recognition_posts as rp')
            ->join('employees as recipient', 'rp.recipient_id', '=', 'recipient.employee_id')
            ->join('departments as department', 'recipient.department_id', '=', 'department.department_id')
            ->leftJoin('recognition_badges as badge', 'rp.badge_id', '=', 'badge.badge_id')
            ->where('rp.moderation_status', 'approved')
            ->where('rp.is_public', true)
            ->whereBetween('rp.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->select('department.name')
            ->selectRaw('SUM(COALESCE(badge.points_value, 1)) as recognition_points')
            ->groupBy('department.department_id', 'department.name')
            ->orderByDesc('recognition_points')
            ->value('department.name') ?? '-';
    }
}
