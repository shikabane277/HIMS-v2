<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecognitionController extends Controller
{
    public function index()
    {
        $stats = [
            'total_posts'      => DB::table('recognition_posts')->where('moderation_status','approved')->count(),
            'badges_given'     => DB::table('recognition_posts')->whereNotNull('badge_id')->count(),
            'total_reactions'  => DB::table('recognition_reactions')->count(),
            'top_dept'         => DB::table('v_recognition_leaderboard')
                                    ->selectRaw('department_name, SUM(total_points) as pts')
                                    ->groupBy('department_name')
                                    ->orderByDesc('pts')->value('department_name') ?? '—',
        ];

        $posts = DB::table('recognition_posts as rp')
            ->join('employees as author','rp.author_id','=','author.employee_id')
            ->join('employees as recip','rp.recipient_id','=','recip.employee_id')
            ->join('departments as ad','author.department_id','=','ad.department_id')
            ->leftJoin('recognition_badges as rb','rp.badge_id','=','rb.badge_id')
            ->leftJoin('recognition_reactions as rrr', function($j){
                $j->on('rp.post_id','=','rrr.post_id');
            })
            ->leftJoin('recognition_comments as rc2','rp.post_id','=','rc2.post_id')
            ->select(
                'rp.post_id','rp.message','rp.post_type','rp.is_featured','rp.created_at',
                DB::raw("CONCAT(author.first_name,' ',author.last_name) AS author_name"),
                'ad.name as author_dept',
                DB::raw("CONCAT(recip.first_name,' ',recip.last_name) AS recipient_name"),
                'rb.badge_name','rb.badge_icon',
                DB::raw('COUNT(DISTINCT rrr.reaction_id) as reactions_count'),
                DB::raw('COUNT(DISTINCT rc2.comment_id) as comments_count')
            )
            ->where('rp.moderation_status','approved')
            ->groupBy('rp.post_id','author.first_name','author.last_name','ad.name',
                      'recip.first_name','recip.last_name','rb.badge_name','rb.badge_icon')
            ->orderByDesc('rp.created_at')->paginate(10);

        $leaderboard = DB::table('v_recognition_leaderboard')
            ->whereMonth('month', now()->month)
            ->orderByDesc('total_points')->limit(10)->get();

        $badges = DB::table('recognition_badges')->where('is_active',true)->get();

        return view('recognition.index', compact('stats','posts','leaderboard','badges'));
    }

    public function createPost()
    {
        $employees = DB::table('employees')->orderBy('first_name')->get();
        $badges    = DB::table('recognition_badges')->where('is_active',true)->get();
        return view('recognition.posts.create', compact('employees','badges'));
    }

    public function storePost(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|string',
            'message'      => 'required|string|max:1000',
        ]);

        DB::table('recognition_posts')->insert([
            'post_id'            => Str::uuid(),
            'author_id'          => auth()->user()->employee_id,
            'recipient_id'       => $request->recipient_id,
            'badge_id'           => $request->badge_id ?: null,
            'post_type'          => $request->post_type ?? 'peer',
            'message'            => $request->message,
            'is_public'          => true,
            'moderation_status'  => 'approved',
            'created_at'         => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('recognition.index')->with('success','Recognition posted!');
    }

    public function react(Request $request, $postId)
    {
        $empId = auth()->user()->employee_id ?? null;
        if (!$empId) return back();

        $exists = DB::table('recognition_reactions')
            ->where('post_id',$postId)->where('employee_id',$empId)->exists();

        if ($exists) {
            DB::table('recognition_reactions')->where('post_id',$postId)->where('employee_id',$empId)->delete();
        } else {
            DB::table('recognition_reactions')->insert([
                'reaction_id'   => Str::uuid(),
                'post_id'       => $postId,
                'employee_id'   => $empId,
                'reaction_type' => $request->reaction_type ?? 'like',
                'created_at'    => now(), 'updated_at' => now(),
            ]);
        }

        return back();
    }

    public function storeComment(Request $request, $postId)
    {
        $request->validate(['comment_text' => 'required|string|max:500']);
        DB::table('recognition_comments')->insert([
            'comment_id'        => Str::uuid(),
            'post_id'           => $postId,
            'author_id'         => auth()->user()->employee_id,
            'comment_text'      => $request->comment_text,
            'moderation_status' => 'approved',
            'created_at'        => now(), 'updated_at' => now(),
        ]);
        return back()->with('success','Comment added.');
    }

    public function createBadge() { return view('recognition.badges.create'); }

    public function storeBadge(Request $request)
    {
        $request->validate(['badge_name'=>'required|string|max:100','points_value'=>'required|integer|min:1']);
        DB::table('recognition_badges')->insert([
            'badge_id'        => Str::uuid(),
            'badge_name'      => $request->badge_name,
            'badge_icon'      => $request->badge_icon ?? '🏅',
            'badge_color'     => $request->badge_color ?? '#16a34a',
            'hospital_value'  => $request->hospital_value,
            'description'     => $request->description,
            'points_value'    => $request->points_value,
            'is_active'       => true,
            'created_at'      => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('recognition.index')->with('success','Badge created!');
    }
}
