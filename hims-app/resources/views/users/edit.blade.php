@extends('layouts.hims')
@section('title','Edit User')
@section('page-title','User Management')
@section('breadcrumb','HIMS / Admin / Users / Edit')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('users.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:36px;height:36px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--hims-primary)">
                        {{ strtoupper(substr($user->name,0,1)) }}
                    </div>
                    <h5 style="margin:0">Edit — {{ $user->name }}</h5>
                </div>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Full Name *</label>
                            <input type="text" name="name" class="hims-input" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Email Address *</label>
                            <input type="email" name="email" class="hims-input" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">New Password <span style="color:#9ca3af;font-weight:400">(leave blank to keep)</span></label>
                            <input type="password" name="password" class="hims-input" placeholder="Min. 8 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="hims-input">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Role *</label>
                            <select name="role" class="hims-input hims-select" required>
                                <option value="staff"      {{ ($user->role ?? 'staff') === 'staff'      ? 'selected' : '' }}>👤 Staff</option>
                                <option value="supervisor" {{ $user->role === 'supervisor' ? 'selected' : '' }}>💼 Supervisor</option>
                                <option value="hr_manager" {{ $user->role === 'hr_manager' ? 'selected' : '' }}>🏢 HR Manager</option>
                                <option value="admin"      {{ $user->role === 'admin'      ? 'selected' : '' }}>🛡️ Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Linked Employee</label>
                            <select name="employee_id" class="hims-input hims-select">
                                <option value="">— Not linked —</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->employee_id }}" {{ $user->employee_id == $emp->employee_id ? 'selected' : '' }}>
                                        {{ $emp->first_name }} {{ $emp->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-between mt-2">
                            <div>
                                @if($user->id !== auth()->id())
                                    <button type="button" class="btn-hims" style="background:#fee2e2;color:#dc2626;border:none;cursor:pointer" onclick="if(confirm('Are you sure you want to delete this user account? This action cannot be undone.')) { document.getElementById('delete-user-form').submit(); }">
                                        <i class="bi bi-trash"></i> Delete User
                                    </button>
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('users.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
                            </div>
                        </div>
                    </div>
                </form>
                @if($user->id !== auth()->id())
                    <form id="delete-user-form" action="{{ route('users.destroy', $user) }}" method="POST" style="display:none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
