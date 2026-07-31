@extends('layouts.hims')
@section('title','Add User')
@section('page-title','User Management')
@section('breadcrumb','HIMS / Admin / Users / New')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('users.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-person-plus-fill"></i> Create System User</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Full Name *</label>
                            <input type="text" name="name" class="hims-input" value="{{ old('name') }}" required placeholder="e.g. Maria Santos">
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Email Address *</label>
                            <input type="email" name="email" class="hims-input" value="{{ old('email') }}" required placeholder="user@hospital.com">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Password *</label>
                            <input type="password" name="password" class="hims-input" required placeholder="Min. 8 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Confirm Password *</label>
                            <input type="password" name="password_confirmation" class="hims-input" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Role *</label>
                            <select name="role" class="hims-input hims-select" required>
                                <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>👤 Staff</option>
                                <option value="supervisor" {{ old('role') === 'supervisor' ? 'selected' : '' }}>💼 Supervisor</option>
                                <option value="hr_manager" {{ old('role') === 'hr_manager' ? 'selected' : '' }}>🏢 HR Manager</option>
                                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>🛡️ Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Link to Employee <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                            <select name="employee_id" class="hims-input hims-select">
                                <option value="">— Not linked —</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-1" style="background:var(--hims-primary-pale);border-radius:8px;padding:12px;font-size:12.5px;color:#6b7280;border:1px solid var(--hims-border)">
                            <strong style="color:var(--hims-primary)">Role access levels:</strong><br>
                            🛡️ Admin — full access &nbsp;|&nbsp; 🏢 HR Manager — all modules &nbsp;|&nbsp; 💼 Supervisor — team management &nbsp;|&nbsp; 👤 Staff — view own data
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('users.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
