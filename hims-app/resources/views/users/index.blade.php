@extends('layouts.hims')
@section('title','User Management')
@section('page-title','User Management')
@section('breadcrumb','HIMS / Admin / Users')
@section('content')

<style>
    @keyframes livePulse {
        0% { transform: scale(0.9); opacity: 0.7; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(0.9); opacity: 0.7; }
    }
    .live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        display: inline-block;
        animation: livePulse 2s infinite ease-in-out;
    }
    @keyframes rowHighlight {
        0% { background-color: #dcfce7 !important; }
        100% { background-color: transparent !important; }
    }
    .row-newly-added {
        animation: rowHighlight 3s ease-out forwards;
    }
    .live-status-pill {
        font-size: 11.5px;
        color: #059669;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 3px 8px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        margin-left: 8px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0;display:flex;align-items:center">
            System Users
            <span class="live-status-pill" id="liveSyncBadge" title="This page automatically reflects newly created accounts in real time">
                <span class="live-dot"></span> Live Sync Active
            </span>
        </h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage login accounts that can access HIMS. New accounts appear immediately.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="userCreateModal"><i class="bi bi-person-plus-fill"></i> Add User</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value" id="stat-total-users">{{ $total }}</div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
            <div class="stat-value" id="stat-admin-users">{{ $users->where('role','admin')->count() }}</div>
            <div class="stat-label">Admins</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-briefcase"></i></div>
            <div class="stat-value" id="stat-manager-users">{{ $users->whereIn('role',['hr_manager','supervisor'])->count() }}</div>
            <div class="stat-label">Managers</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-lock"></i></div>
            <div class="stat-value" id="stat-locked-users">{{ $users->filter(fn($u) => isset($u->locked_until) && $u->locked_until && now()->lt($u->locked_until))->count() }}</div>
            <div class="stat-label">Locked Accounts</div>
        </div>
    </div>
</div>

{{-- Filters Toolbar --}}
<div class="hims-card mb-3" style="padding:12px 18px">
    <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-center" id="usersFilterForm">
        <div class="col-md-5">
            <div style="position:relative">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px"></i>
                <input type="text" name="search" id="userSearchInput" class="hims-input" style="padding-left:36px;height:38px"
                       placeholder="Search by name or email..." value="{{ request('search') }}" autocomplete="off">
            </div>
        </div>
        <div class="col-md-3">
            <select name="role" id="userRoleFilter" class="hims-input hims-select" style="height:38px">
                <option value="">All Roles</option>
                <option value="admin" @selected(request('role') === 'admin')>Administrator</option>
                <option value="hr_manager" @selected(request('role') === 'hr_manager')>HR Manager</option>
                <option value="supervisor" @selected(request('role') === 'supervisor')>Supervisor</option>
                <option value="staff" @selected(request('role') === 'staff')>Staff</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="sort" id="userSortFilter" class="hims-input hims-select" style="height:38px">
                <option value="latest" @selected(request('sort', 'latest') === 'latest')>Newest First</option>
                <option value="name" @selected(request('sort') === 'name')>Name (A-Z)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-hims btn-hims-outline btn-sm" style="height:38px;flex:1"><i class="bi bi-funnel"></i> Filter</button>
            @if(request()->hasAny(['search', 'role', 'sort']))
                <a href="{{ route('users.index') }}" class="btn-hims btn-hims-ghost btn-sm" style="height:38px;padding:8px" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
            @endif
        </div>
    </form>
</div>

<div id="liveUserAlert" class="hims-alert success mb-3" style="display:none">
    <i class="bi bi-check-circle-fill"></i> <span id="liveUserAlertMsg"></span>
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
            <tbody id="usersTableBody">
                @include('users._table_rows', ['users' => $users])
            </tbody>
        </table>
    </div>
    <div id="usersPaginationContainer" style="{{ $users->hasPages() ? '' : 'display:none;' }}padding:16px 22px;border-top:1px solid var(--hims-border)">
        {{ $users->links() }}
    </div>
</div>

@push('modals')
{{-- Create User Modal --}}
<div class="hims-modal-backdrop" id="userCreateModal" role="dialog" aria-modal="true" aria-labelledby="userCreateModalTitle">
    <div class="hims-modal" style="max-width:580px">
        <div class="hims-modal-header">
            <h4 id="userCreateModalTitle"><i class="bi bi-person-plus-fill"></i> Add System User</h4>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('users.store') }}" id="ajaxUserCreateForm">
            @csrf
            <div class="hims-modal-body">
                <div id="userCreateError" class="hims-alert error mb-3" style="display:none"></div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label">Full Name *</label>
                        <input type="text" name="name" class="hims-input" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="col-12">
                        <label class="hims-label">Email Address *</label>
                        <input type="email" name="email" class="hims-input" required placeholder="user@hospital.com">
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label">Password *</label>
                        <input type="password" name="password" class="hims-input" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label">Confirm Password *</label>
                        <input type="password" name="password_confirmation" class="hims-input" required minlength="8" placeholder="Repeat password">
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label">Role *</label>
                        <select name="role" class="hims-input hims-select" required>
                            <option value="staff">Staff</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="hr_manager">HR Manager</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label">Link to Employee <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                        <select name="employee_id" class="hims-input hims-select" id="modalEmployeeSelect">
                            <option value="">— Not linked —</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12" style="background:var(--hims-primary-pale);border-radius:8px;padding:10px 12px;font-size:12px;color:#6b7280;border:1px solid var(--hims-border)">
                        <strong style="color:var(--hims-primary)">Access:</strong>
                        <span class="badge bg-white text-dark border me-1">Admin</span> Full &nbsp;|&nbsp;
                        <span class="badge bg-white text-dark border me-1">HR Manager</span> All modules &nbsp;|&nbsp;
                        <span class="badge bg-white text-dark border me-1">Supervisor</span> Team &nbsp;|&nbsp;
                        <span class="badge bg-white text-dark border">Staff</span> Self
                    </div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-ghost" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary" id="btnSubmitCreateUser">
                    <i class="bi bi-check-circle"></i> Create User
                </button>
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

@include('partials.modal-js')

@push('scripts')
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const createForm = document.getElementById('ajaxUserCreateForm');
    const submitBtn = document.getElementById('btnSubmitCreateUser');
    const errorBox = document.getElementById('userCreateError');
    const tableBody = document.getElementById('usersTableBody');
    const paginationContainer = document.getElementById('usersPaginationContainer');
    const liveAlert = document.getElementById('liveUserAlert');
    const liveAlertMsg = document.getElementById('liveUserAlertMsg');

    // ── 1. In-Page AJAX User Creation ──────────────────────────────
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (submitBtn.disabled) return;

            errorBox.style.display = 'none';
            errorBox.textContent = '';
            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" style="width:0.9rem;height:0.9rem;border-width:2px;"></span> Creating...';

            try {
                const formData = new FormData(createForm);
                const res = await fetch(createForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                let data = null;
                try {
                    data = await res.json();
                } catch (_) {
                    data = null;
                }

                if (!res.ok) {
                    const msg = data?.message || Object.values(data?.errors || {})[0]?.[0] || (res.status === 419 ? 'Session expired. Please refresh the page.' : `Unable to create user (Status ${res.status}).`);
                    errorBox.textContent = msg;
                    errorBox.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    return;
                }

                // Success: Close modal & reset form
                if (window.himsModal && window.himsModal.close) {
                    window.himsModal.close('userCreateModal');
                }
                createForm.reset();

                // Show success banner
                if (liveAlert && liveAlertMsg) {
                    liveAlertMsg.textContent = data?.message || 'User created successfully.';
                    liveAlert.style.display = 'flex';
                    setTimeout(() => { liveAlert.style.display = 'none'; }, 5000);
                }

                // Immediately pull updated rows & highlight the new row
                await refreshUsersTable(true);

            } catch (err) {
                errorBox.textContent = err?.message || 'A connection error occurred. Please try again.';
                errorBox.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    // ── 2. Dynamic Live Sync Table Refresh ─────────────────────────
    let isFetching = false;
    async function refreshUsersTable(highlightFirst = false) {
        if (isFetching) return;
        isFetching = true;

        try {
            const form = document.getElementById('usersFilterForm');
            const url = new URL(form ? form.action : window.location.href);

            if (form) {
                const formData = new FormData(form);
                for (const [k, v] of formData.entries()) {
                    if (v) url.searchParams.set(k, v);
                    else url.searchParams.delete(k);
                }
            }

            const res = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) return;
            const data = await res.json();

            if (data.html && tableBody) {
                tableBody.innerHTML = data.html;

                if (highlightFirst) {
                    const firstRow = tableBody.querySelector('tr');
                    if (firstRow) {
                        firstRow.classList.add('row-newly-added');
                        setTimeout(() => firstRow.classList.remove('row-newly-added'), 3500);
                    }
                }
            }

            if (data.pagination_html !== undefined && paginationContainer) {
                paginationContainer.innerHTML = data.pagination_html;
                paginationContainer.style.display = data.pagination_html ? '' : 'none';
            }

            if (data.stats) {
                const totalEl = document.getElementById('stat-total-users');
                const adminEl = document.getElementById('stat-admin-users');
                const managerEl = document.getElementById('stat-manager-users');
                const lockedEl = document.getElementById('stat-locked-users');

                if (totalEl) totalEl.textContent = data.stats.total;
                if (adminEl) adminEl.textContent = data.stats.admins;
                if (managerEl) managerEl.textContent = data.stats.managers;
                if (lockedEl) lockedEl.textContent = data.stats.locked;
            }

        } catch (e) {
            // Silently ignore transient network drops during background sync
        } finally {
            isFetching = false;
        }
    }

    // Expose for external calls
    window.refreshUsersTable = refreshUsersTable;

    // ── 3. Background Sync Triggers ────────────────────────────────
    // (a) Periodic sync every 10 seconds
    setInterval(() => {
        if (!document.hidden) refreshUsersTable(false);
    }, 10000);

    // (b) Immediate sync when tab regains focus or visibility
    window.addEventListener('focus', () => refreshUsersTable(false));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshUsersTable(false);
    });

    // (c) Sync when AI assistant or background action mutates data
    window.addEventListener('hims:data-mutated', () => refreshUsersTable(true));

    // (d) Live Filter inputs debounce
    const searchInput = document.getElementById('userSearchInput');
    const roleSelect = document.getElementById('userRoleFilter');
    const sortSelect = document.getElementById('userSortFilter');

    let searchTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => refreshUsersTable(false), 300);
        });
    }
    if (roleSelect) {
        roleSelect.addEventListener('change', () => refreshUsersTable(false));
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', () => refreshUsersTable(false));
    }
})();
</script>
@endpush
@endsection
