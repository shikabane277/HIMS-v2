<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrainingController extends Controller
{
    public function index()
    {
        $stats = [
            'upcoming_sessions'    => DB::table('training_sessions')->where('status','scheduled')->where('session_date','>=',now())->count(),
            'total_registrations'  => DB::table('training_registrations')->count(),
            'avg_attendance'       => round(DB::table('training_registrations')->where('status','attended')->count() / max(DB::table('training_registrations')->count(),1) * 100),
            'avg_feedback_score'   => number_format(DB::table('training_feedback')->avg('overall_rating') ?? 0, 1),
        ];

        $sessions = DB::table('training_sessions as ts')
            ->join('employees as i','ts.instructor_id','=','i.employee_id')
            ->leftJoin('training_venues as tv','ts.venue_id','=','tv.venue_id')
            ->leftJoin('training_registrations as tr','ts.session_id','=','tr.session_id')
            ->select('ts.*','tv.venue_name',
                DB::raw("CONCAT(i.first_name,' ',i.last_name) AS instructor_name"),
                DB::raw('COUNT(tr.registration_id) as registered_count'))
            ->where('ts.session_date','>=',now())
            ->groupBy('ts.session_id','tv.venue_name','i.first_name','i.last_name')
            ->orderBy('ts.session_date')->limit(10)->get();

        $venues = DB::table('training_venues')->orderBy('venue_name')->get();

        $feedback = DB::table('training_feedback as tf')
            ->join('training_sessions as ts','tf.session_id','=','ts.session_id')
            ->join('employees as e','tf.employee_id','=','e.employee_id')
            ->select('tf.*','ts.title as session_title',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"))
            ->orderByDesc('tf.submitted_at')->limit(10)->get();

        return view('training.index', compact('stats','sessions','venues','feedback'));
    }

    public function createSession() { return view('training.sessions.create', [
        'venues'      => DB::table('training_venues')->where('is_active',true)->get(),
        'instructors' => DB::table('employees')->orderBy('first_name')->get(),
        'courses'     => DB::table('courses')->where('is_active',true)->get(),
    ]); }

    public function storeSession(Request $request)
    {
        $request->validate([
            'title'         => 'required|string|max:300',
            'category'      => 'required|string',
            'instructor_id' => 'required|string',
            'session_date'  => 'required|date',
            'start_time'    => 'required',
            'end_time'      => 'required|after:start_time',
            'capacity'      => 'required|integer|min:1',
        ]);

        DB::table('training_sessions')->insert([
            'session_id'    => Str::uuid(),
            'title'         => $request->title,
            'category'      => $request->category,
            'instructor_id' => $request->instructor_id,
            'venue_id'      => $request->venue_id ?: null,
            'session_date'  => $request->session_date,
            'start_time'    => $request->start_time,
            'end_time'      => $request->end_time,
            'capacity'      => $request->capacity,
            'cpd_hours'     => $request->cpd_hours ?? 0,
            'description'   => $request->description,
            'status'        => 'scheduled',
            'created_by'    => $this->currentEmployeeId(),
            'created_at'    => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('training.index')->with('success','Training session scheduled successfully.');
    }

    public function register($sessionId)
    {
        $session = DB::table('training_sessions')->where('session_id',$sessionId)->first();
        abort_if(!$session, 404);

        $registered = DB::table('training_registrations')
            ->where('session_id',$sessionId)->count();

        if ($registered >= $session->capacity) {
            return back()->with('error','This session is fully booked.');
        }

        $empId = auth()->user()->employee_id ?? null;
        if (!$empId) return back()->with('error','No linked employee profile.');

        $exists = DB::table('training_registrations')
            ->where('session_id',$sessionId)->where('employee_id',$empId)->exists();

        if (!$exists) {
            DB::table('training_registrations')->insert([
                'registration_id'   => Str::uuid(),
                'session_id'        => $sessionId,
                'employee_id'       => $empId,
                'status'            => 'registered',
                'registration_date' => now(),
            ]);
        }

        return redirect()->route('training.index')->with('success','Registered for session successfully.');
    }

    public function venuesIndex() { return view('training.venues.index', ['venues' => DB::table('training_venues')->get()]); }
    public function createVenue() { return view('training.venues.create'); }

    public function showSession($id)
    {
        $session = DB::table('training_sessions as ts')
            ->join('employees as i','ts.instructor_id','=','i.employee_id')
            ->leftJoin('training_venues as tv','ts.venue_id','=','tv.venue_id')
            ->leftJoin('training_registrations as tr','ts.session_id','=','tr.session_id')
            ->leftJoin('training_feedback as tf','ts.session_id','=','tf.session_id')
            ->select('ts.*','tv.venue_name',
                DB::raw("CONCAT(i.first_name,' ',i.last_name) AS instructor_name"),
                DB::raw('COUNT(DISTINCT tr.registration_id) as registered_count'),
                DB::raw('AVG(tf.overall_rating) as avg_rating'))
            ->where('ts.session_id',$id)
            ->groupBy('ts.session_id','tv.venue_name','i.first_name','i.last_name')
            ->first();

        abort_if(!$session, 404);

        $registrations = DB::table('training_registrations as tr')
            ->join('employees as e','tr.employee_id','=','e.employee_id')
            ->join('departments as d','e.department_id','=','d.department_id')
            ->select('tr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'd.name as department_name')
            ->where('tr.session_id',$id)
            ->get();

        return view('training.sessions.show', compact('session','registrations'));
    }

    public function storeVenue(Request $request)
    {
        $request->validate(['venue_name'=>'required|string|max:150','capacity'=>'required|integer|min:1']);
        DB::table('training_venues')->insert([
            'venue_id'    => Str::uuid(),
            'venue_name'  => $request->venue_name,
            'building'    => $request->building,
            'floor'       => $request->floor,
            'capacity'    => $request->capacity,
            'is_active'   => true,
            'created_at'  => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('training.venues.index')->with('success','Venue added.');
    }
}

