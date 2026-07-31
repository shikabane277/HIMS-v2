@extends('layouts.hims')
@section('title','User Management')
@section('page-title','User Management')
@section('breadcrumb','HIMS / Admin / Users')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">System Users</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage accounts that can log in to HIMS.</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-person-plus-fill"></i> Add User</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $total }}</div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">🛡️</div>
            <div class="stat-value">{{ $users->where('role','admin')->count() }}</div>
            <div class="stat-label">Admins</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">💼</div>
            <div class="stat-value">{{ $users->whereIn('role',['hr_manager','supervisor'])->count() }}</div>
            <div class="stat-label">Managers</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-value">{{ $users->where('role','staff')->count() }}</div>
            <div class="stat-label">Staff</div>
        </div>
    </div>
</div>

<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Linked Employee</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--hims-primary);font-size:13px;flex-shrink:0">
                                {{ strtoupper(substr($user->name,0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px">{{ $user->name }}</div>
                                @if($user->id === auth()->id())
                                    <span style="font-size:11px;color:var(--hims-primary)">(You)</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:#6b7280">{{ $user->email }}</td>
                    <td>
                        <span class="hims-badge {{ $user->role === 'admin' ? 'red' : ($user->role === 'hr_manager' ? 'blue' : ($user->role === 'supervisor' ? 'yellow' : 'gray')) }}">
                            {{ ucfirst(str_replace('_',' ',$user->role ?? 'staff')) }}
                        </span>
                    </td>
                    <td style="font-size:13px;color:#6b7280">{{ $user->employee_id ? '✓ Linked' : '—' }}</td>
                    <td style="font-size:12px;color:#9ca3af">{{ $user->created_at->format('M d, Y') }}</td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="{{ route('users.edit', $user) }}" class="btn-hims btn-hims-ghost btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                             @if($user->id !== auth()->id())
                             <a href="#" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;cursor:pointer;border-radius:8px;padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;justify-content:center" onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this user?')) { document.getElementById('delete-form-{{ $user->id }}').submit(); }">
                                 <i class="bi bi-trash"></i>
                             </a>
                             <form id="delete-form-{{ $user->id }}" method="POST" action="{{ route('users.destroy', $user) }}" style="display:none">
                                 @csrf
                                 @method('DELETE')
                             </form>
                             @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $users->links() }}</div>
    @endif
</div>
@endsection
