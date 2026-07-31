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
    <a href="{{ route('competency.credentials.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-patch-plus-fill"></i> Add Credential</a>
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
            <thead><tr><th>Employee</th><th>Type</th><th>Number</th><th>Issued By</th><th>Expiry</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($credentials ?? [] as $cred)
                @php
                    $expired = $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->isPast();
                    $expiring = !$expired && $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->diffInDays(now()) <= 30;
                @endphp
                <tr>
                    <td><strong>{{ $cred->employee_name ?? '—' }}</strong></td>
                    <td>{{ $cred->credential_type }}</td>
                    <td style="font-family:monospace;font-size:12.5px">{{ $cred->credential_number ?? '—' }}</td>
                    <td style="font-size:12.5px">{{ $cred->issuing_body ?? '—' }}</td>
                    <td style="{{ $expired ? 'color:var(--hims-danger);font-weight:700' : ($expiring ? 'color:#d97706;font-weight:600' : '') }};font-size:12.5px">
                        {{ $cred->expiry_date ? \Carbon\Carbon::parse($cred->expiry_date)->format('M d, Y') : 'No expiry' }}
                    </td>
                    <td><span class="hims-badge {{ $expired ? 'red' : ($expiring ? 'yellow' : 'green') }}">
                        {{ $expired ? 'Expired' : ($expiring ? 'Expiring Soon' : 'Valid') }}
                    </span></td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:48px">
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
@endsection
