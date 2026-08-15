<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(25);
        $total = User::count();

        $aiSettings = DB::table('system_settings')->pluck('value', 'key')->all();

        return view('users.index', compact('users', 'total', 'aiSettings'));
    }

    public function create()
    {
        $employees = DB::table('employees')
            ->whereNotIn('employee_id', function ($q) {
                $q->select('employee_id')->from('users')->whereNotNull('employee_id');
            })
            ->orderBy('first_name')
            ->get();

        return view('users.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|in:admin,hr_manager,supervisor,staff',
            'employee_id' => 'nullable|string|exists:employees,employee_id|unique:users,employee_id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'employee_id' => $request->employee_id ?: null,
            'email_verified_at' => now(),
        ]);

        AuditTrail::record('create_user', 'users', (string) $user->id, afterState: [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return redirect()->route('users.index')->with('success', "User \"{$request->name}\" created successfully.");
    }

    public function edit(User $user)
    {
        $employees = DB::table('employees')
            ->where(function ($q) use ($user) {
                $q->whereNotIn('employee_id', function ($sq) {
                    $sq->select('employee_id')->from('users')->whereNotNull('employee_id');
                })->orWhere('employee_id', $user->employee_id);
            })
            ->orderBy('first_name')
            ->get();

        return view('users.edit', compact('user', 'employees'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'role' => 'required|in:admin,hr_manager,supervisor,staff',
            'employee_id' => "nullable|string|exists:employees,employee_id|unique:users,employee_id,{$user->id}",
        ]);

        if ($user->role === 'admin' && $request->role !== 'admin' && $this->adminCount() <= 1) {
            return back()->withInput()->with('error',
                'This is the only administrator account. Promote another user to admin before changing this one.');
        }

        $beforeState = $user->toArray();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'employee_id' => $request->employee_id ?: null,
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => ['confirmed', Password::min(8)]]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        AuditTrail::record('update_user', 'users', (string) $user->id, beforeState: $beforeState, afterState: [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" updated.");
    }

    public function unlockAccount($id)
    {
        DB::table('users')->where('id', $id)->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
            'updated_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User account unlocked successfully.');
    }

    public function updateAiSettings(Request $request)
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'ai_include_comments'],
            ['value' => $request->has('ai_include_comments') ? '1' : '0', 'updated_at' => now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'ai_redact_names'],
            ['value' => $request->has('ai_redact_names') ? '1' : '0', 'updated_at' => now()]
        );

        return redirect()->route('users.index')->with('success', 'AI Data-Sharing Settings updated.');
    }

    public function auditHistory(Request $request)
    {
        $resourceType = $request->query('resource_type');
        $resourceId = $request->query('resource_id');

        $logs = DB::table('audit_trails as a')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
            ->when($resourceType, fn ($q) => $q->where('a.resource_type', $resourceType))
            ->when($resourceId, fn ($q) => $q->where('a.resource_id', $resourceId))
            ->select('a.*', 'u.name as actor_name')
            ->orderByDesc('a.timestamp')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === 'admin' && $this->adminCount() <= 1) {
            return back()->with('error',
                'This is the only administrator account and cannot be deleted.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User \"{$name}\" deleted.");
    }

    private function adminCount(): int
    {
        return User::where('role', 'admin')->count();
    }
}
