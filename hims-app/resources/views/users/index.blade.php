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
    <div class="d-flex gap-2">
        <button type="button" class="btn-hims btn-hims-outline" data-modal-open="aiDataSettingsModal"><i class="bi bi-shield-lock"></i> AI Data Settings</button>
        <a href="{{ route('users.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-person-plus-fill"></i> Add User</a>
    </div>
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
            <div class="stat-icon">🔒</div>
            <div class="stat-value">{{ $users->filter(fn($u) => isset($u->locked_until) && $u->locked_until && now()->lt($u->locked_until))->count() }}</div>
            <div class="stat-label">Locked Accounts</div>
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
                    <th>Status</th>
                    <th>Linked Employee</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                @php $isLocked = isset($user->locked_until) && $user->locked_until && now()->lt($user->locked_until); @endphp
                <tr>
                    <td data-label="Name">
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
                    <td data-label="Email" style="font-size:13px;color:#6b7280">{{ $user->email }}</td>
                    <td data-label="Role">
                        <span class="hims-badge {{ $user->role === 'admin' ? 'red' : ($user->role === 'hr_manager' ? 'blue' : ($user->role === 'supervisor' ? 'yellow' : 'gray')) }}">
                            {{ ucfirst(str_replace('_',' ',$user->role ?? 'staff')) }}
                        </span>
                    </td>
                    <td data-label="Status">
                        @if($isLocked)
                            <span class="hims-badge red" title="Locked until {{ $user->locked_until }}"><i class="bi bi-lock-fill"></i> Locked</span>
                        @else
                            <span class="hims-badge green">Active</span>
                        @endif
                    </td>
                    <td data-label="Linked Employee" style="font-size:13px;color:#6b7280">{{ $user->employee_id ? '✓ Linked' : '—' }}</td>
                    <td data-label="Actions">
                        <div style="display:flex;gap:6px;align-items:center">
                            @if($isLocked)
                            <button type="button" class="btn-hims btn-hims-outline btn-sm" data-modal-open="unlockModal-{{ $user->id }}"><i class="bi bi-unlock"></i> Unlock</button>
                            @endif
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

@push('modals')
{{-- AI Data Settings Modal --}}
<div class="hims-modal-backdrop" id="aiDataSettingsModal" style="display:none">
    <div class="hims-modal" style="max-width:520px">
        <div class="hims-modal-header">
            <h4><i class="bi bi-shield-lock"></i> AI Data-Sharing Settings</h4>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('settings.ai-data-sharing') }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="form-check-label d-flex align-items-center gap-2" style="font-weight:600">
                        <input type="checkbox" name="ai_include_comments" value="1" @checked(($aiSettings['ai_include_comments'] ?? '1') === '1')>
                        Include supervisors' written comments in AI gap-analysis
                    </label>
                    <p style="font-size:12px;color:#6b7280;margin:4px 0 0 24px">Allows the AI prompt to receive verbatim review feedback text.</p>
                </div>

                <div class="mb-3">
                    <label class="form-check-label d-flex align-items-center gap-2" style="font-weight:600">
                        <input type="checkbox" name="ai_redact_names" value="1" @checked(($aiSettings['ai_redact_names'] ?? '1') === '1')>
                        Redact employee & patient names before sending to AI
                    </label>
                    <p style="font-size:12px;color:#6b7280;margin:4px 0 0 24px">Automatically replaces names and PII in review feedback with placeholders.</p>
                </div>

                <div class="p-3" style="background:#f8fafc;border-radius:8px;border:1px solid var(--hims-border)">
                    <div style="font-size:12px;color:#6b7280">Active AI Provider</div>
                    <div style="font-size:14px;font-weight:600;color:var(--hims-primary)">
                        {{ ucfirst(config('services.ai.driver', 'gemini')) }}
                        @if(config('services.ai.driver') === 'compatible')
                            ({{ config('services.ai.compatible_label', 'Groq') }})
                        @endif
                    </div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-ghost" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

{{-- Unlock Modals --}}
@foreach($users as $user)
@if(isset($user->locked_until) && $user->locked_until && now()->lt($user->locked_until))
<div class="hims-modal-backdrop" id="unlockModal-{{ $user->id }}" style="display:none">
    <div class="hims-modal" style="max-width:460px">
        <div class="hims-modal-header">
            <h4><i class="bi bi-unlock"></i> Unlock Account</h4>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('users.unlock', $user->id) }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">User Account</label>
                    <input type="text" class="hims-input" value="{{ $user->name }} ({{ $user->email }})" readonly>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Failed Login Attempts</label>
                    <input type="text" class="hims-input" value="{{ $user->failed_login_attempts ?? 5 }}" readonly>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Locked Until</label>
                    <input type="text" class="hims-input" value="{{ $user->locked_until }}" readonly>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-ghost" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary">Unlock Now</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endpush
@endsection
