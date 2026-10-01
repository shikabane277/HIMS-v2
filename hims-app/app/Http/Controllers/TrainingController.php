<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'upcoming_sessions' => DB::table('training_sessions')->where('status', 'scheduled')->where('session_date', '>=', now())->count(),
            'total_registrations' => DB::table('training_registrations')->count(),
            'avg_attendance' => round(DB::table('training_registrations')->where('status', 'attended')->count() / max(DB::table('training_registrations')->count(), 1) * 100),
            'avg_feedback_score' => number_format(DB::table('training_feedback')->avg('overall_rating') ?? 0, 1),
        ];

        // The Upcoming Sessions card's category dropdown was a hardcoded list of
        // four labels with no name, no form and no handler — it filtered nothing.
        // Options now come from the categories sessions actually carry, so the
        // control cannot offer a category that returns an empty table.
        $categories = DB::table('training_sessions')
            ->select('category')->distinct()->orderBy('category')->pluck('category');
        $category = $request->query('category');

        $sessions = DB::table('training_sessions as ts')
            ->join('employees as i', 'ts.instructor_id', '=', 'i.employee_id')
            ->leftJoin('training_venues as tv', 'ts.venue_id', '=', 'tv.venue_id')
            ->leftJoin('training_registrations as tr', 'ts.session_id', '=', 'tr.session_id')
            ->select('ts.*', 'tv.venue_name',
                DB::raw("CONCAT(i.first_name,' ',i.last_name) AS instructor_name"),
                DB::raw('COUNT(tr.registration_id) as registered_count'))
            ->where('ts.session_date', '>=', now())
            ->when($category, fn ($q) => $q->where('ts.category', $category))
            ->groupBy('ts.session_id', 'tv.venue_name', 'i.first_name', 'i.last_name')
            ->orderBy('ts.session_date')->limit(10)->get();

        $venues = DB::table('training_venues')->orderBy('venue_name')->get();

        // The Venue Availability card lists every venue with an Available /
        // Offline badge, so it needs all of them; the session modal must only
        // offer the ones that can actually be booked. Filtered off the same
        // collection rather than re-queried — one round trip, and the two lists
        // cannot drift apart.
        $activeVenues = $venues->where('is_active', true);

        // For the session modal, which the page now hosts instead of a create
        // page. Only the three columns the <option> prints — a select * here is
        // how a renamed column becomes a 500 inside a @foreach nobody tested.
        $instructors = DB::table('employees')
            ->select('employee_id', 'first_name', 'last_name')
            ->orderBy('first_name')->get();

        $feedback = DB::table('training_feedback as tf')
            ->join('training_sessions as ts', 'tf.session_id', '=', 'ts.session_id')
            ->join('employees as e', 'tf.employee_id', '=', 'e.employee_id')
            ->select('tf.*', 'ts.title as session_title',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) AS employee_name"))
            ->orderByDesc('tf.submitted_at')->limit(10)->get();

        return view('training.index', compact('stats', 'sessions', 'venues', 'activeVenues', 'instructors', 'feedback', 'categories', 'category'));
    }

    /**
     * Schedule a session. There is no matching create() — the form is a modal on
     * the Training index (?new=session), so this is posted to directly.
     */
    public function storeSession(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:300',
            'category' => 'required|string',
            'instructor_id' => 'required|string|exists:employees,employee_id',
            'session_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'capacity' => 'required|integer|min:1',
            'venue_id' => 'nullable|string|exists:training_venues,venue_id',
        ]);

        // Overlap detection: Venue
        if ($request->venue_id) {
            $venueConflict = DB::table('training_sessions')
                ->where('venue_id', $request->venue_id)
                ->where('session_date', $request->session_date)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->first();

            if ($venueConflict) {
                return back()->withInput()->with('error', "Venue booking conflict: Venue is already booked for '{$venueConflict->title}' ({$venueConflict->start_time} - {$venueConflict->end_time}).");
            }
        }

        // Overlap detection: Instructor
        if ($request->instructor_id) {
            $instConflict = DB::table('training_sessions')
                ->where('instructor_id', $request->instructor_id)
                ->where('session_date', $request->session_date)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->first();

            if ($instConflict) {
                return back()->withInput()->with('error', "Instructor schedule conflict: Instructor is already teaching '{$instConflict->title}' ({$instConflict->start_time} - {$instConflict->end_time}).");
            }
        }

        DB::table('training_sessions')->insert([
            'session_id' => Str::uuid(),
            'title' => $request->title,
            'category' => $request->category,
            'instructor_id' => $request->instructor_id,
            'venue_id' => $request->venue_id ?: null,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'capacity' => $request->capacity,
            'cpd_hours' => $request->cpd_hours ?? 0,
            'description' => $request->description,
            'status' => 'scheduled',
            'created_by' => $this->currentEmployeeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('training.index')->with('success', 'Training session scheduled successfully.');
    }

    public function updateSession(Request $request, $id)
    {
        abort_unless(auth()->user()->can('manage-training'), 403);
        $session = DB::table('training_sessions')->where('session_id', $id)->first();
        abort_if(! $session, 404);

        $request->validate([
            'title' => 'required|string|max:300',
            'category' => 'required|string',
            'instructor_id' => 'required|string|exists:employees,employee_id',
            'session_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'capacity' => 'required|integer|min:1',
            'venue_id' => 'nullable|string|exists:training_venues,venue_id',
        ]);

        if ($request->venue_id) {
            $conflict = DB::table('training_sessions')
                ->where('venue_id', $request->venue_id)
                ->where('session_date', $request->session_date)
                ->where('session_id', '!=', $id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->first();

            if ($conflict) {
                return back()->withInput()->with('error', "Venue booking conflict: Venue is already booked for '{$conflict->title}' ({$conflict->start_time} - {$conflict->end_time}).");
            }
        }

        DB::table('training_sessions')->where('session_id', $id)->update([
            'title' => $request->title,
            'category' => $request->category,
            'instructor_id' => $request->instructor_id,
            'venue_id' => $request->venue_id ?: null,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'capacity' => $request->capacity,
            'cpd_hours' => $request->cpd_hours ?? 0,
            'description' => $request->description,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Training session updated successfully.');
    }

    public function rescheduleSession(Request $request, $id)
    {
        abort_unless(auth()->user()->can('manage-training'), 403);
        $session = DB::table('training_sessions')->where('session_id', $id)->first();
        abort_if(! $session, 404);

        $request->validate([
            'session_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'venue_id' => 'nullable|string|exists:training_venues,venue_id',
        ]);

        $venueId = $request->venue_id ?: $session->venue_id;

        if ($venueId) {
            $conflict = DB::table('training_sessions')
                ->where('venue_id', $venueId)
                ->where('session_date', $request->session_date)
                ->where('session_id', '!=', $id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->first();

            if ($conflict) {
                return back()->with('error', "Cannot reschedule: Venue conflict with '{$conflict->title}' ({$conflict->start_time} - {$conflict->end_time}).");
            }
        }

        DB::table('training_sessions')->where('session_id', $id)->update([
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'venue_id' => $venueId,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Training session rescheduled successfully.');
    }

    public function cancelSession(Request $request, $id)
    {
        abort_unless(auth()->user()->can('manage-training'), 403);
        $session = DB::table('training_sessions')->where('session_id', $id)->first();
        abort_if(! $session, 404);

        DB::table('training_sessions')->where('session_id', $id)->update([
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);

        DB::table('training_registrations')->where('session_id', $id)->where('status', 'registered')->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', 'Training session cancelled.');
    }

    public function register($sessionId)
    {
        $session = DB::table('training_sessions')->where('session_id', $sessionId)->first();
        abort_if(! $session, 404);

        if ($session->status === 'cancelled') {
            return back()->with('error', 'Cannot register for a cancelled session.');
        }

        $empId = auth()->user()->employee_id ?? null;
        if (! $empId) {
            return back()->with('error', 'No linked employee profile.');
        }

        $exists = DB::table('training_registrations')
            ->where('session_id', $sessionId)->where('employee_id', $empId)->first();

        if ($exists) {
            return back()->with('info', "You are already {$exists->status} for this session.");
        }

        $registeredCount = DB::table('training_registrations')
            ->where('session_id', $sessionId)
            ->where('status', 'registered')
            ->count();

        $isWaitlisted = $registeredCount >= $session->capacity;
        $status = $isWaitlisted ? 'waitlisted' : 'registered';

        DB::table('training_registrations')->insert([
            'registration_id' => Str::uuid(),
            'session_id' => $sessionId,
            'employee_id' => $empId,
            'status' => $status,
            'registration_date' => now(),
        ]);

        if ($isWaitlisted) {
            $waitlistPos = DB::table('training_registrations')
                ->where('session_id', $sessionId)
                ->where('status', 'waitlisted')
                ->count();

            return redirect()->route('training.index')->with('warning', "Session is at full capacity ({$session->capacity}). You have been added to the waitlist (Position #{$waitlistPos}).");
        }

        return redirect()->route('training.index')->with('success', 'Registered for session successfully.');
    }

    public function venuesIndex()
    {
        return view('training.venues.index', ['venues' => DB::table('training_venues')->get()]);
    }

    public function showSession($id)
    {
        $session = DB::table('training_sessions as ts')
            ->join('employees as i', 'ts.instructor_id', '=', 'i.employee_id')
            ->leftJoin('training_venues as tv', 'ts.venue_id', '=', 'tv.venue_id')
            ->leftJoin('training_registrations as tr', 'ts.session_id', '=', 'tr.session_id')
            ->leftJoin('training_feedback as tf', 'ts.session_id', '=', 'tf.session_id')
            ->select('ts.*', 'tv.venue_name',
                DB::raw("CONCAT(i.first_name,' ',i.last_name) AS instructor_name"),
                DB::raw('COUNT(DISTINCT tr.registration_id) as registered_count'),
                DB::raw('AVG(tf.overall_rating) as avg_rating'))
            ->where('ts.session_id', $id)
            ->groupBy('ts.session_id', 'tv.venue_name', 'i.first_name', 'i.last_name')
            ->first();

        abort_if(! $session, 404);

        $registrations = DB::table('training_registrations as tr')
            ->join('employees as e', 'tr.employee_id', '=', 'e.employee_id')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->select('tr.*',
                DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"),
                'd.name as department_name')
            ->where('tr.session_id', $id)
            ->get();

        return view('training.sessions.show', compact('session', 'registrations'));
    }

    /**
     * Add a venue. There is no matching create() — the form is a modal on the
     * Venues page (?new=venue), so this is posted to directly.
     */
    public function storeVenue(Request $request)
    {
        $request->validate(['venue_name' => 'required|string|max:150', 'capacity' => 'required|integer|min:1']);
        DB::table('training_venues')->insert([
            'venue_id' => Str::uuid(),
            'venue_name' => $request->venue_name,
            'building' => $request->building,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('training.venues.index')->with('success', 'Venue added.');
    }

    public function updateVenue(Request $request, string $id)
    {
        abort_unless(auth()->user()->can('manage-venues'), 403);

        $venue = DB::table('training_venues')->where('venue_id', $id)->first();
        abort_if(! $venue, 404);

        $request->validate([
            'venue_name' => 'required|string|max:150',
            'building' => 'nullable|string|max:100',
            'floor' => 'nullable|string|max:20',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        DB::table('training_venues')->where('venue_id', $id)->update([
            'venue_name' => $request->venue_name,
            'building' => $request->building,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'updated_at' => now(),
        ]);

        return redirect()->route('training.venues.index')->with('success', 'Venue updated.');
    }

    public function destroyVenue(string $id)
    {
        abort_unless(auth()->user()->can('manage-venues'), 403);

        $venue = DB::table('training_venues')->where('venue_id', $id)->first();
        abort_if(! $venue, 404);

        $inUse = DB::table('training_sessions')->where('venue_id', $id)->exists();
        if ($inUse) {
            return redirect()->route('training.venues.index')->with('error', 'Cannot delete a venue with linked training sessions. Mark it offline instead.');
        }

        DB::table('training_venues')->where('venue_id', $id)->delete();

        return redirect()->route('training.venues.index')->with('success', 'Venue deleted.');
    }

    /**
     * Attendance check-in: instructors mark who showed up.
     *
     * Gated on manage-training (admin/hr_manager/supervisor). The session owner
     * and admin/hr can check anyone in; a supervisor not running the session
     * can only mark their own department's staff.
     */
    public function checkIn(Request $request, string $sessionId)
    {
        abort_unless(auth()->user()->can('manage-training'), 403);

        $session = DB::table('training_sessions')->where('session_id', $sessionId)->first();
        abort_if(! $session, 404);

        $user = auth()->user();
        $isInstructor = $session->instructor_id === $this->currentEmployeeId();

        $request->validate([
            'registrations' => 'required|array',
            'registrations.*' => 'required|in:attended,no_show',
        ]);

        foreach ($request->registrations as $registrationId => $status) {
            $registration = DB::table('training_registrations as tr')
                ->join('employees as e', 'tr.employee_id', '=', 'e.employee_id')
                ->where('tr.registration_id', $registrationId)
                ->where('tr.session_id', $sessionId)
                ->select('tr.*', 'e.department_id')
                ->first();

            if (! $registration) {
                continue;
            }

            // Instructors may check in anyone; supervisors may only check in their
            // own department unless admin/hr.
            if (! $isInstructor && ! $user->seesWholeOrganisation()) {
                if ($registration->department_id !== $user->departmentId()) {
                    continue;
                }
            }

            DB::table('training_registrations')->where('registration_id', $registrationId)->update([
                'status' => $status,
                'check_in_time' => $status === 'attended' ? now() : null,
                'check_in_method' => $status === 'attended' ? 'manual' : null,
            ]);
        }

        return back()->with('success', 'Attendance recorded.');
    }

    /**
     * Store training feedback.
     *
     * There is no matching feedbackForm() — the form is a modal on the session
     * page (?feedback=1), so this is posted to directly. That page decides
     * whether to render the button at all; these three checks are what actually
     * enforce it, since a hidden button is a courtesy and not a rule.
     */
    public function storeFeedback(Request $request, string $sessionId)
    {
        $session = DB::table('training_sessions')->where('session_id', $sessionId)->first();
        abort_if(! $session, 404);

        $empId = $this->currentEmployeeId();

        if (! $empId) {
            return back()->with('error', 'Your account is not linked to an employee profile.');
        }

        $registration = DB::table('training_registrations')
            ->where('session_id', $sessionId)
            ->where('employee_id', $empId)
            ->where('status', 'attended')
            ->first();

        if (! $registration) {
            return back()->with('error', 'Feedback is only available after attending the session.');
        }

        // Moved here from the deleted GET page, which was the only place it
        // lived. `training_feedback` is UNIQUE (session_id, employee_id), so
        // without this check a second submit — double-click, back button, a tab
        // left open — raises a QueryException and reaches the user as a 500
        // instead of a sentence.
        $alreadySubmitted = DB::table('training_feedback')
            ->where('session_id', $sessionId)
            ->where('employee_id', $empId)
            ->exists();

        if ($alreadySubmitted) {
            return back()->with('error', 'You have already submitted feedback for this session.');
        }

        $request->validate([
            'overall_rating' => 'required|integer|min:1|max:5',
            'content_rating' => 'nullable|integer|min:1|max:5',
            'instructor_rating' => 'nullable|integer|min:1|max:5',
            'venue_rating' => 'nullable|integer|min:1|max:5',
            'comments' => 'nullable|string|max:2000',
        ]);

        DB::table('training_feedback')->insert([
            'feedback_id' => Str::uuid(),
            'session_id' => $sessionId,
            'employee_id' => $empId,
            'overall_rating' => $request->overall_rating,
            'content_rating' => $request->content_rating,
            'instructor_rating' => $request->instructor_rating,
            'venue_rating' => $request->venue_rating,
            'comments' => $request->comments,
            'submitted_at' => now(),
        ]);

        return redirect()->route('training.sessions.show', $sessionId)
            ->with('success', 'Thank you for your feedback.');
    }
}
