@extends('layouts.hims')
@section('title', 'Credential Compliance Report')
@section('page-title', 'Reporting & Analytics')
@section('breadcrumb', 'HIMS / Reports / Compliance')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Licensing & Credential Compliance Report (PB-19)</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            JCI SQE and Philippine PRC professional license validity, renewal tracking, and risk exposure.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost">
            <i class="bi bi-arrow-left"></i> Reports Hub
        </a>
        <button type="button" class="btn-hims btn-hims-outline" onclick="window.print()">
            <i class="bi bi-printer"></i> Print / PDF
        </button>
        <a href="{{ route('reports.compliance.export') }}" class="btn-hims btn-hims-primary">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </div>
</div>

{{-- Metric Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-value">{{ $statusCounts['valid'] }}</div>
            <div class="stat-label">Valid Credentials</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value">{{ $statusCounts['expiring_soon'] }}</div>
            <div class="stat-label">Expiring (30-90 Days)</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-value">{{ $statusCounts['critical'] }}</div>
            <div class="stat-label">Critical (< 30 Days)</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f3f4f6;color:#6b7280"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value">{{ $statusCounts['expired'] }}</div>
            <div class="stat-label">Expired / Non-Compliant</div>
        </div>
    </div>
</div>

{{-- Visual Chart Section --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="hims-card h-100">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart-fill"></i> Compliance Status Distribution</h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="padding:20px">
                <div style="width:260px;height:260px;position:relative">
                    <canvas id="complianceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="hims-card h-100">
            <div class="card-header">
                <h5><i class="bi bi-shield-check"></i> JCI SQE Compliance Standards</h5>
            </div>
            <div class="card-body">
                <p style="font-size:13.5px;color:#4b5563;line-height:1.6">
                    In accordance with <strong>Joint Commission International (JCI) Staff Qualifications and Education (SQE) Standard 3</strong>, 
                    all medical, nursing, and allied health staff must maintain valid, verified professional credentials without lapse.
                </p>
                <div class="p-3 mb-3" style="background:#f8fafc;border-left:4px solid #2563eb;border-radius:4px;font-size:13px">
                    <strong>Automated Warning System:</strong> Staff with credentials expiring within 90 days receive weekly renewal notifications;
                    unrenewed credentials under 30 days trigger automated escalation to the Department Head and HR Credentialing Committee.
                </div>
                <div class="d-flex gap-3 mt-3">
                    <span class="hims-badge green"><i class="bi bi-circle-fill" style="font-size:8px"></i> Valid: Fully qualified</span>
                    <span class="hims-badge yellow"><i class="bi bi-circle-fill" style="font-size:8px"></i> Expiring: Action required</span>
                    <span class="hims-badge red"><i class="bi bi-circle-fill" style="font-size:8px"></i> Expired: Grounding risk</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Detailed Credential Table --}}
<div class="hims-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-table"></i> Staff Credentials Ledger</h5>
        <span class="text-muted" style="font-size:13px">{{ $credentials->count() }} total records</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Department</th>
                    <th>Credential / License</th>
                    <th>Issuing Body</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Verification</th>
                </tr>
            </thead>
            <tbody>
                @forelse($credentials as $c)
                <tr>
                    <td data-label="Staff Member">
                        <strong>{{ $c->first_name }} {{ $c->last_name }}</strong>
                        <div style="font-size:11.5px;color:#6b7280;font-family:monospace">{{ $c->employee_code }}</div>
                    </td>
                    <td data-label="Department">{{ $c->department_name }}</td>
                    <td data-label="Credential / License">
                        <strong>{{ $c->credential_type }}</strong>
                        @if($c->license_number)
                            <div style="font-size:11.5px;color:#6b7280">Lic #: {{ $c->license_number }}</div>
                        @endif
                    </td>
                    <td data-label="Issuing Body">{{ $c->issuing_body }}</td>
                    <td data-label="Expiry Date" style="font-size:13px;font-weight:600">
                        {{ \Carbon\Carbon::parse($c->expiry_date)->format('M d, Y') }}
                    </td>
                    <td data-label="Status">
                        @if($c->status_label === 'valid')
                            <span class="hims-badge green">Valid</span>
                        @elseif($c->status_label === 'expiring_soon')
                            <span class="hims-badge yellow">Expiring Soon</span>
                        @elseif($c->status_label === 'critical')
                            <span class="hims-badge red">Critical (< 30d)</span>
                        @else
                            <span class="hims-badge gray" style="background:#fee2e2;color:#dc2626">Expired</span>
                        @endif
                    </td>
                    <td data-label="Verification">
                        @if($c->verified_at)
                            <span class="hims-badge blue" title="Verified on {{ $c->verified_at }}"><i class="bi bi-shield-check"></i> Verified</span>
                        @else
                            <span class="hims-badge gray">Pending</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:32px">No credentials found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('complianceChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Valid', 'Expiring Soon', 'Critical (< 30d)', 'Expired'],
            datasets: [{
                data: [
                    {{ $statusCounts['valid'] }},
                    {{ $statusCounts['expiring_soon'] }},
                    {{ $statusCounts['critical'] }},
                    {{ $statusCounts['expired'] }}
                ],
                backgroundColor: ['#16a34a', '#d97706', '#dc2626', '#9ca3af'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 } }
                }
            },
            cutout: '65%'
        }
    });
});
</script>
@endpush
@endsection
