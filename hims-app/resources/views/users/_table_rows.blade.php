@forelse($users as $user)
@php $isLocked = isset($user->locked_until) && $user->locked_until && now()->lt($user->locked_until); @endphp
<tr id="user-row-{{ $user->id }}" data-user-id="{{ $user->id }}">
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
        @if(!($user->is_active ?? true))
            <span class="hims-badge red"><i class="bi bi-person-x-fill"></i> Deactivated</span>
        @elseif($isLocked)
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
            <form action="{{ route('users.toggle-active', $user->id) }}" method="POST" style="display:inline">
                @csrf
                @if($user->is_active ?? true)
                    <button type="submit" class="btn-hims btn-sm" style="background:#fef3c7;color:#92400e;border:none;cursor:pointer;border-radius:8px;padding:6px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px" onclick="return confirm('Deactivate this account? User will not be able to log in.')">
                        <i class="bi bi-person-x"></i> Deactivate
                    </button>
                @else
                    <button type="submit" class="btn-hims btn-sm" style="background:#dcfce7;color:#166534;border:none;cursor:pointer;border-radius:8px;padding:6px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px" onclick="return confirm('Reactivate this account?')">
                        <i class="bi bi-person-check"></i> Activate
                    </button>
                @endif
            </form>
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
@empty
<tr>
    <td colspan="6" class="text-center" style="padding:40px;color:#9ca3af">
        <i class="bi bi-people" style="font-size:32px;display:block;margin-bottom:8px"></i>
        No system users found.
    </td>
</tr>
@endforelse
