<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(25);
        $total = User::count();
        return view('users.index', compact('users', 'total'));
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
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => ['required', 'confirmed', Password::min(8)],
            'role'        => 'required|in:admin,hr_manager,supervisor,staff',
            // One login per employee profile, and it must be a real profile.
            'employee_id' => 'nullable|string|exists:employees,employee_id|unique:users,employee_id',
        ]);

        User::create([
            'name'               => $request->name,
            'email'              => $request->email,
            'password'           => Hash::make($request->password),
            'role'               => $request->role,
            'employee_id'        => $request->employee_id ?: null,
            'email_verified_at'  => now(),
        ]);

        return redirect()->route('users.index')->with('success', "User \"{$request->name}\" created successfully.");
    }

    public function edit(User $user)
    {
        $employees = DB::table('employees')
            ->where(function($q) use ($user) {
                $q->whereNotIn('employee_id', function($sq) {
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
            'name'        => 'required|string|max:255',
            'email'       => "required|email|unique:users,email,{$user->id}",
            'role'        => 'required|in:admin,hr_manager,supervisor,staff',
            'employee_id' => "nullable|string|exists:employees,employee_id|unique:users,employee_id,{$user->id}",
        ]);

        // Don't allow the last administrator to be demoted — that would leave
        // nobody able to manage users, roles or departments.
        if ($user->role === 'admin' && $request->role !== 'admin' && $this->adminCount() <= 1) {
            return back()->withInput()->with('error',
                'This is the only administrator account. Promote another user to admin before changing this one.');
        }

        $user->update([
            'name'        => $request->name,
            'email'       => $request->email,
            'role'        => $request->role,
            'employee_id' => $request->employee_id ?: null,
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => ['confirmed', Password::min(8)]]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" updated.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Deleting the last admin would lock everyone out of user management.
        if ($user->role === 'admin' && $this->adminCount() <= 1) {
            return back()->with('error',
                'This is the only administrator account and cannot be deleted.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User \"{$name}\" deleted.");
    }

    /**
     * Deleting a login does not touch the employee record it points at, so the
     * only thing at risk here is administrative access itself.
     */
    private function adminCount(): int
    {
        return User::where('role', 'admin')->count();
    }
}
