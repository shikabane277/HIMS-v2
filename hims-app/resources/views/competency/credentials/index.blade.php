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
    @can('manage-competency')
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="credentialCreateModal"><i class="bi bi-patch-plus-fill"></i> Add Credential</button>
    @endcan
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value">{{ $stats['total'] ?? 0 }}</div><div class="stat-label">Total Credentials</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $stats['valid'] ?? 0 }}</div><div class="stat-label">Valid</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⚠️</div><div class="stat-value">{{ $stats['expiring'] ?? 0 }}</div><div class="stat-label">Expiring (30 days)</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🔴</div><div class="stat-value">{{ $stats['expired'] ?? 0 }}</div><div class="stat-label">Expired</div></div></div>
</div>
<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Type</th><th>Number</th><th>Issued By</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($credentials ?? [] as $cred)
                @php
                    $expired = $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->isPast();
                    $expiring = !$expired && $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->diffInDays(now()) <= 30;
                    $rawNum = $cred->credential_number ?? '';
                    $maskedNum = $rawNum ? ('••••-••••-' . (strlen($rawNum) > 4 ? substr($rawNum, -4) : '1234')) : '—';
                @endphp
                <tr id="credential-{{ $cred->credential_id }}" style="scroll-margin-top:84px">
                    <td><strong>{{ $cred->employee_name ?? '—' }}</strong></td>
                    <td>{{ $cred->credential_type }}</td>
                    <td style="font-family:monospace;font-size:12.5px">
                        @if($rawNum)
                        <span id="cred-num-{{ $cred->credential_id }}">{{ $maskedNum }}</span>
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" style="padding:1px 5px;font-size:11px" onclick="let d = document.getElementById('cred-num-{{ $cred->credential_id }}'); d.innerText = d.innerText === '{{ $maskedNum }}' ? '{{ $rawNum }}' : '{{ $maskedNum }}';">Show</button>
                        @else
                        —
                        @endif
                    </td>
                    <td style="font-size:12.5px">{{ $cred->issuing_body ?? '—' }}</td>
                    <td style="{{ $expired ? 'color:var(--hims-danger);font-weight:700' : ($expiring ? 'color:#d97706;font-weight:600' : '') }};font-size:12.5px">
                        {{ $cred->expiry_date ? \Carbon\Carbon::parse($cred->expiry_date)->format('M d, Y') : 'No expiry' }}
                    </td>
                    <td><span class="hims-badge {{ $expired ? 'red' : ($expiring ? 'yellow' : 'green') }}">
                        {{ $expired ? 'Expired' : ($expiring ? 'Expiring Soon' : 'Valid') }}
                    </span></td>
                    <td>
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
    @if(isset($credentials) && method_exists($credentials,'hasPages') && $credentials->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $credentials->links() }}</div>
    @endif
</div>

@can('manage-competency')
    @include('competency.credentials._create-modal')
@endcan

@can('view-audit-history')
@include('partials._audit_history_modal')
@endcan
@endsection
