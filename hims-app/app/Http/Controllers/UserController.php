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
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => 'required|in:admin,hr_manager,supervisor,staff',
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
            'name'  => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'role'  => 'required|in:admin,hr_manager,supervisor,staff',
        ]);

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
        \Illuminate\Support\Facades\Log::info("UserController@destroy called for user ID: " . $user->id);
        if ($user->id === auth()->id()) {
            \Illuminate\Support\Facades\Log::warning("UserController@destroy: Prevented self-deletion for user ID: " . $user->id);
            return back()->with('error', 'You cannot delete your own account.');
        }
        $name = $user->name;
        $user->delete();
        \Illuminate\Support\Facades\Log::info("UserController@destroy: Successfully deleted user ID: " . $user->id);
        return redirect()->route('users.index')->with('success', "User \"{$name}\" deleted.");
    }
}
