@extends('layouts.hims')
@section('title','Credentials & Licenses')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / Credentials')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Credentials & Licenses</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Track professional licenses, board certifications, and clinical credentials.</p>
    </div>
</div>

@php
    $hr1Connected = ($hr1Integration->last_sync_status ?? null) === 'success' && !empty($hr1Integration->base_url);
@endphp

@if(! $hr1Connected)
<div class="hims-alert warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Integration Notice:</strong> External system <strong>HR1 (Employee Master &amp; Clinical Credentials)</strong> is currently <strong>Not Connected</strong>. Showing local sample data.
</div>
@else
<div class="hims-alert success mb-4">
    <i class="bi bi-check-circle-fill"></i>
    <strong>Integration Active:</strong> HR1 credentials feed is connected and synchronizing via backend automation.
</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clipboard-data"></i></div><div class="stat-value">{{ $stats['total'] ?? 0 }}</div><div class="stat-label">Total Credentials</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-value">{{ $stats['valid'] ?? 0 }}</div><div class="stat-label">Valid</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value">{{ $stats['expiring'] ?? 0 }}</div><div class="stat-label">Expiring (30 days)</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div><div class="stat-value">{{ $stats['expired'] ?? 0 }}</div><div class="stat-label">Expired</div></div></div>
</div>
<div class="hims-card">
    <div class="hims-sensitive-wrapper">
        <div class="hims-sensitive-records {{ ! \App\Support\RecordAccess::isUnlocked() ? 'is-blurred' : '' }}">
            <div class="card-body" style="padding:0">
                <table class="hims-table">
            <thead><tr><th>Employee</th><th>Type</th><th>Number</th><th>Issued By</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($credentials ?? [] as $cred)
                @php
                    $expired = $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->isPast();
                    $expiring = !$expired && $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->diffInDays(now()) <= 30;
                    $rawNum = $cred->credential_number ?? '';
                    $maskedNum = $rawNum ? ('••••-••••-' . (strlen($rawNum) > 4 ? substr($rawNum, -4) : '••••')) : '—';
                @endphp
                <tr id="credential-{{ $cred->credential_id }}" style="scroll-margin-top:84px">
                    <td data-label="Employee"><strong>{{ $cred->employee_name ?? '—' }}</strong></td>
                    <td data-label="Type">{{ $cred->credential_type }}</td>
                    <td data-label="Number" style="font-family:monospace;font-size:12.5px">
                        @if($rawNum)
                        <span id="cred-num-{{ $cred->credential_id }}" data-masked="{{ $maskedNum }}" data-full="{{ $rawNum }}">{{ $maskedNum }}</span>
                        {{-- Values travel in data-* attributes, never inside inline JS, so a credential number containing a quote cannot break out into script. --}}
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" style="padding:1px 5px;font-size:11px" onclick="var d = document.getElementById('cred-num-{{ $cred->credential_id }}'); d.textContent = d.textContent === d.dataset.masked ? d.dataset.full : d.dataset.masked;">Show</button>
                        @else
                        —
                        @endif
                    </td>
                    <td data-label="Issued By" style="font-size:12.5px">{{ $cred->issuing_body ?? '—' }}</td>
                    <td data-label="Expiry" style="{{ $expired ? 'color:var(--hims-danger);font-weight:700' : ($expiring ? 'color:#d97706;font-weight:600' : '') }};font-size:12.5px">
                        {{ $cred->expiry_date ? \Carbon\Carbon::parse($cred->expiry_date)->format('M d, Y') : 'No expiry' }}
                    </td>
                    <td data-label="Status"><span class="hims-badge {{ $expired ? 'red' : ($expiring ? 'yellow' : 'green') }}">
                        {{ $expired ? 'Expired' : ($expiring ? 'Expiring Soon' : 'Valid') }}
                    </span>
                    @if($cred->verified_at)
                        <span class="hims-badge blue" title="Verified {{ \Carbon\Carbon::parse($cred->verified_at)->format('M d, Y') }}{{ $cred->verifier_name ? ' by '.$cred->verifier_name : '' }}"><i class="bi bi-patch-check"></i> Verified</span>
                    @else
                        <span class="hims-badge" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0" title="Not yet checked against the issuing body">Unverified</span>
                    @endif
                    </td>
                    <td data-label="Actions">
                        @if(! $cred->verified_at && auth()->user()?->hasRole('admin', 'hr_manager'))
                        <form method="POST" action="{{ route('competency.credentials.verify', $cred->credential_id) }}" style="display:inline" onsubmit="return confirm('Confirm you have checked this credential with the issuing body (primary-source verification)?');">
                            @csrf
                            <button type="submit" class="btn-hims btn-hims-ghost btn-sm"><i class="bi bi-patch-check"></i> Verify</button>
                        </form>
                        @endif
                        @can('view-audit-history')
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-history-resource-type="employee_credentials" data-history-resource-id="{{ $cred->credential_id }}"><i class="bi bi-clock-history"></i> History</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:48px">
                    <div style="font-size:36px;margin-bottom:10px">🏅</div>
                    No credentials on file.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
    @if(! \App\Support\RecordAccess::isUnlocked())
        @include('partials.record-access-overlay', ['slot' => 'Clinical credentials, board license numbers, and practitioner records are protected. Enter the Record Access Password to unblur.'])
    @endif
</div>
    @if(isset($credentials) && method_exists($credentials,'hasPages') && $credentials->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $credentials->links() }}</div>
    @endif
</div>



@can('view-audit-history')
@include('partials._audit_history_modal')
@endcan
@include('partials.modal-js')
@endsection
