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
        <div class="d-flex gap-2">
            <button type="button" class="btn-hims btn-hims-outline" data-modal-open="importCredentialModal">
                <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
            </button>
            <button type="button" class="btn-hims btn-hims-primary" data-modal-open="credentialCreateModal">
                <i class="bi bi-patch-plus-fill"></i> Add Credential
            </button>
        </div>
    @endcan
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clipboard-data"></i></div><div class="stat-value">{{ $stats['total'] ?? 0 }}</div><div class="stat-label">Total Credentials</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-value">{{ $stats['valid'] ?? 0 }}</div><div class="stat-label">Valid</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value">{{ $stats['expiring'] ?? 0 }}</div><div class="stat-label">Expiring (30 days)</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div><div class="stat-value">{{ $stats['expired'] ?? 0 }}</div><div class="stat-label">Expired</div></div></div>
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
                    <td data-label="Employee"><strong>{{ $cred->employee_name ?? '—' }}</strong></td>
                    <td data-label="Type">{{ $cred->credential_type }}</td>
                    <td data-label="Number" style="font-family:monospace;font-size:12.5px">
                        @if($rawNum)
                        <span id="cred-num-{{ $cred->credential_id }}">{{ $maskedNum }}</span>
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" style="padding:1px 5px;font-size:11px" onclick="let d = document.getElementById('cred-num-{{ $cred->credential_id }}'); d.innerText = d.innerText === '{{ $maskedNum }}' ? '{{ $rawNum }}' : '{{ $maskedNum }}';">Show</button>
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
                    </span></td>
                    <td data-label="Actions">
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

<div class="hims-modal-backdrop" id="importCredentialModal" role="dialog" aria-modal="true">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5><i class="bi bi-file-earmark-arrow-up"></i> Import Credentials from CSV</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('competency.credentials.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="hims-modal-body">
                <p style="font-size:13px;color:#6b7280;margin-bottom:14px">
                    Upload a CSV file to batch-import licenses and certifications linked to staff employee codes.
                </p>
                <div class="mb-3">
                    <label class="hims-label" for="cred_csv">Select CSV File *</label>
                    <input type="file" name="csv_file" id="cred_csv" class="hims-input" accept=".csv,text/csv" required>
                </div>
                <div class="p-3" style="background:#f8fafc;border-radius:6px;font-size:12.5px;color:#4b5563">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Expected Columns:</strong>
                        <a href="{{ route('competency.credentials.import.template') }}" class="btn-hims btn-hims-ghost btn-sm" style="padding:2px 8px;font-size:12px">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                    </div>
                    <code style="display:block;font-size:11px;color:#2563eb;word-break:break-all">
                        employee_code, credential_type, credential_number, issuing_body, issue_date, expiry_date
                    </code>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-upload"></i> Upload & Import</button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('view-audit-history')
@include('partials._audit_history_modal')
@endcan
@include('partials.modal-js')
@endsection
