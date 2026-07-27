@extends('layouts.hims')
@section('title','Competency Management')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Competency Framework</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Monitor skills gaps, clinical credentials, and JCI competency compliance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('competency.assessments.create') }}" class="btn-hims btn-hims-outline"><i class="bi bi-clipboard-check"></i> New Assessment</a>
        <a href="{{ route('competency.credentials.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-patch-check"></i> Add Credential</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🎯</div><div class="stat-value">{{ $stats['total_competencies'] ?? 0 }}</div><div class="stat-label">Total Competencies</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value">{{ $stats['avg_gap'] ?? '0.0' }}</div><div class="stat-label">Avg Proficiency Gap</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🟡</div><div class="stat-value">{{ $stats['expiring_soon'] ?? 0 }}</div><div class="stat-label">Expiring Credentials</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🔴</div><div class="stat-value">{{ $stats['expired'] ?? 0 }}</div><div class="stat-label">Expired Credentials</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- Skills Gap Matrix -->
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-grid-3x3"></i> Department Skills Gap Matrix</h5>
                <select class="hims-input hims-select" style="width:160px;padding:6px 12px;font-size:13px">
                    <option>All Departments</option>
                    @foreach($departments ?? [] as $dept)
                    <option>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Competency</th><th>Required</th><th>Avg Score</th><th>Gap</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($gap_matrix ?? [] as $row)
                        <tr>
                            <td><strong>{{ $row->competency_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $row->competency_code }}</div></td>
                            <td><span style="font-weight:600">{{ $row->required_proficiency }}/5</span></td>
                            <td><span style="font-weight:600">{{ number_format($row->avg_score ?? 0,1) }}/5</span></td>
                            <td>
                                <span class="gap-chip {{ ($row->gap ?? 0) >= 0 ? 'positive' : 'negative' }}">
                                    {{ ($row->gap ?? 0) >= 0 ? '+' : '' }}{{ $row->gap ?? 0 }}
                                </span>
                            </td>
                            <td>
                                <span class="hims-badge {{ ($row->gap ?? 0) >= 0 ? 'green' : (($row->gap ?? 0) >= -1 ? 'yellow' : 'red') }}">
                                    {{ ($row->gap ?? 0) >= 0 ? 'Met' : (($row->gap ?? 0) >= -1 ? 'Minor Gap' : 'Critical Gap') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No assessment data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Credentials Status -->
    <div class="col-lg-5">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-patch-check-fill"></i> Credential Alerts</h5>
                <a href="{{ route('competency.credentials.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($credential_alerts ?? [] as $cred)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:{{ $cred->status === 'expired' ? '#fee2e2' : '#fef3c7' }};border-radius:8px">
                    <div>
                        <div style="font-size:13px;font-weight:600">{{ $cred->employee_name }}</div>
                        <div style="font-size:11.5px;color:#6b7280">{{ $cred->credential_type }}</div>
                        <div style="font-size:11px;margin-top:2px">Expires: <strong>{{ $cred->expiry_date }}</strong></div>
                    </div>
                    <span class="hims-badge {{ $cred->status === 'expired' ? 'red' : 'yellow' }}">
                        {{ ucfirst(str_replace('_',' ',$cred->status)) }}
                    </span>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:24px">No credential alerts. All licenses are current ✅</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Competency Domains -->
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-diagram-3"></i> Competency Domains</h5>
        <a href="{{ route('competency.domains.create') }}" class="btn-hims btn-hims-primary btn-sm"><i class="bi bi-plus"></i> Add Domain</a>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Domain</th><th>Categories</th><th>Competencies</th><th>JCI Reference</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($domains ?? [] as $domain)
                <tr>
                    <td><strong>{{ $domain->domain_name }}</strong></td>
                    <td>{{ $domain->categories_count ?? 0 }}</td>
                    <td>{{ $domain->competencies_count ?? 0 }}</td>
                    <td><span class="hims-badge blue">{{ $domain->jci_codes ?? 'SQE' }}</span></td>
                    <td><a href="{{ route('competency.domains.show', $domain->domain_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No domains configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
