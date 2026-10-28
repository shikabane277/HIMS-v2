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
        @can('manage-competency-framework')
            <a href="{{ route('competency.role-requirements.index') }}" class="btn-hims btn-hims-outline">
                <i class="bi bi-shield-check"></i> Role Requirements
            </a>
        @endcan
    </div>
</div>

@php
    $hr1Connected = ($integrations['hr1']->last_sync_status ?? null) === 'success' && !empty($integrations['hr1']->base_url);
    $hr2Connected = ($integrations['hr2']->last_sync_status ?? null) === 'success' && !empty($integrations['hr2']->base_url);
@endphp

@if(! $hr1Connected || ! $hr2Connected)
<div class="hims-alert warning mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
        <div>
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Integration Notice:</strong>
            @if(! $hr1Connected && ! $hr2Connected)
                External HR systems (HR1 &amp; HR2) are currently <strong>Not Connected</strong>. Showing local sample data.
            @elseif(! $hr1Connected)
                <strong>HR1 (Credentials)</strong> is <strong>Not Connected</strong>. HR2 is connected.
            @else
                <strong>HR2 (Competencies)</strong> is <strong>Not Connected</strong>. HR1 is connected.
            @endif
        </div>
        <div class="d-flex align-items-center gap-3" style="font-size:12px">
            <span>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $hr1Connected ? '#16a34a' : '#d97706' }}"></span>
                HR1: {{ $hr1Connected ? 'Connected' : 'Not Connected' }}
            </span>
            <span>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $hr2Connected ? '#16a34a' : '#d97706' }}"></span>
                HR2: {{ $hr2Connected ? 'Connected' : 'Not Connected' }}
            </span>
        </div>
    </div>
</div>
@else
<div class="hims-alert success mb-4">
    <i class="bi bi-check-circle-fill"></i>
    <strong>Integration Active:</strong> External HR systems (HR1 &amp; HR2) are connected and synchronizing via backend automation.
</div>
@endif


<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-bullseye"></i></div><div class="stat-value">{{ $stats['total_competencies'] ?? 0 }}</div><div class="stat-label">Total Competencies</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-bar-chart-line"></i></div><div class="stat-value">{{ $stats['avg_gap'] ?? '0.0' }}</div><div class="stat-label">Avg Proficiency Gap</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clock-history"></i></div><div class="stat-value">{{ $stats['expiring_soon'] ?? 0 }}</div><div class="stat-label">Expiring Credentials</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div><div class="stat-value">{{ $stats['expired'] ?? 0 }}</div><div class="stat-label">Expired Credentials</div></div></div>
</div>

{{-- Competency Analytics & Trends --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-bullseye text-primary-hims"></i> Domain Proficiency vs Benchmark</h5>
                <span class="hims-badge blue">Required vs Assessed (1-5)</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px">
                    <canvas id="competencyDomainChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-shield-check text-primary-hims"></i> Credential Compliance Health</h5>
                <span class="hims-badge green">Clinical Standing</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px;display:flex;align-items:center;justify-content:center">
                    <canvas id="credentialHealthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Skills Gap Matrix -->
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-grid-3x3"></i> Department Skills Gap Matrix</h5>
                {{-- GET so the filter is shareable/bookmarkable and survives a
                     refresh, and onchange-submit so picking a department applies
                     it — there is no Apply button to hunt for. Same shape as the
                     position filter on succession/index. --}}
                <form method="GET" action="{{ route('competency.index') }}">
                    <select name="department_id" onchange="this.form.submit()"
                            class="hims-input hims-select" style="width:190px;padding:6px 12px;font-size:13px">
                        <option value="">All Departments</option>
                        @foreach($departments ?? [] as $dept)
                        <option value="{{ $dept->department_id }}" @selected(($filterDepartmentId ?? null) === $dept->department_id)>
                            {{ $dept->name }}
                        </option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Competency</th><th>Required</th><th>Avg Score</th><th>Gap</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($gap_matrix ?? [] as $row)
                        <tr>
                            <td data-label="Competency"><strong>{{ $row->competency_name }}</strong><div style="font-size:11px;color:#9ca3af">{{ $row->competency_code }}</div></td>
                            <td data-label="Required"><span style="font-weight:600">{{ $row->required_proficiency }}/5</span></td>
                            <td data-label="Avg Score"><span style="font-weight:600">{{ number_format($row->avg_score ?? 0,1) }}/5</span></td>
                            <td data-label="Gap">
                                <span class="gap-chip {{ ($row->gap ?? 0) >= 0 ? 'positive' : 'negative' }}">
                                    {{ ($row->gap ?? 0) >= 0 ? '+' : '' }}{{ $row->gap ?? 0 }}
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="hims-badge {{ ($row->gap ?? 0) >= 0 ? 'green' : (($row->gap ?? 0) >= -1 ? 'yellow' : 'red') }}">
                                    {{ ($row->gap ?? 0) >= 0 ? 'Met' : (($row->gap ?? 0) >= -1 ? 'Minor Gap' : 'Critical Gap') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">
                            {{ ($filterDepartmentId ?? null) ? 'No assessments recorded for this department yet.' : 'No assessment data yet.' }}
                        </td></tr>
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
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Domain</th><th>Categories</th><th>Competencies</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($domains ?? [] as $domain)
                <tr>
                    <td data-label="Domain">
                        <strong>{{ $domain->domain_name }}</strong>
                        @if(!empty($domain->description))
                            <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ Str::limit($domain->description, 60) }}</div>
                        @endif
                    </td>
                    <td data-label="Categories">{{ $domain->categories_count ?? 0 }}</td>
                    <td data-label="Competencies">{{ $domain->competencies_count ?? 0 }}</td>
                    <td data-label="Status">
                        @if($domain->is_active ?? true)
                            <span class="hims-badge green">Active</span>
                        @else
                            <span class="hims-badge gray">Inactive</span>
                        @endif
                    </td>
                    <td data-label="Actions">
                        <a href="{{ route('competency.domains.show', $domain->domain_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">No domains configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    // 1. Competency Domains Bar Chart
    const domainCanvas = document.getElementById('competencyDomainChart');
    if (domainCanvas) {
        const domainLabels = {!! json_encode($domainLabels ?? []) !!};
        const domainRequired = {!! json_encode($domainRequired ?? []) !!};
        const domainAssessed = {!! json_encode($domainAssessed ?? []) !!};

        new Chart(domainCanvas, {
            type: 'bar',
            data: {
                labels: domainLabels.length ? domainLabels : ['Clinical Practice', 'Patient Safety', 'Ethics & Law', 'Leadership', 'Informatics'],
                datasets: [
                    {
                        label: 'Required Benchmark',
                        data: domainLabels.length ? domainRequired : [4.0, 4.5, 4.0, 3.5, 3.8],
                        backgroundColor: 'rgba(2, 132, 199, 0.25)',
                        borderColor: '#0284c7',
                        borderWidth: 1.5,
                        borderRadius: 5,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Current Assessed Avg',
                        data: domainLabels.length ? domainAssessed : [3.5, 4.1, 3.8, 3.2, 3.4],
                        backgroundColor: 'rgba(5, 150, 105, 0.85)',
                        borderColor: '#059669',
                        borderWidth: 1.5,
                        borderRadius: 5,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter' } }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Credential Health Donut Chart
    const credCanvas = document.getElementById('credentialHealthChart');
    if (credCanvas) {
        const credData = {!! json_encode(array_values($credentialChartData ?? [])) !!};
        const credLabels = {!! json_encode(array_keys($credentialChartData ?? [])) !!};
        const hasData = credData.some(v => v > 0);

        new Chart(credCanvas, {
            type: 'doughnut',
            data: {
                labels: credLabels,
                datasets: [{
                    data: hasData ? credData : [45, 4, 1],
                    backgroundColor: ['#059669', '#d97706', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' credentials';
                            }
                        }
                    }
                },
                cutout: '62%'
            }
        });
    }
});
</script>
@endpush
@endsection
