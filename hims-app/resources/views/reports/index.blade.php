@extends('layouts.hims')
@section('title', 'Reports & Analytics')
@section('page-title', 'Reports & Analytics')
@section('breadcrumb', 'HIMS / Reports')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Hospital Reports & Analytics</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Performance appraisals, compliance audit exports, and credential verification metrics.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="hims-card" style="height:100%;display:flex;flex-direction:column;justify-content:space-between">
            <div class="card-body" style="padding:24px">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(14,165,233,0.12);color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:16px">
                    <i class="bi bi-bar-chart-line-fill"></i>
                </div>
                <h4 style="font-size:17px;font-weight:700;margin-bottom:8px">Performance Evaluation Report</h4>
                <p style="color:#6b7280;font-size:13px;line-height:1.6">
                    Review cycle results, overall rating distributions (1.0 to 5.0), department score comparisons, and completion statuses. Supports interactive charts, printable view, and CSV export.
                </p>
                <div style="font-size:12px;color:#9ca3af;margin-top:12px">
                    <span style="font-weight:600;color:var(--hims-text-dark)">{{ $reviewsCount }}</span> completed reviews across <span style="font-weight:600;color:var(--hims-text-dark)">{{ $cyclesCount }}</span> cycles.
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px;background:var(--hims-bg-subtle);border-top:1px solid var(--hims-border)">
                <a href="{{ route('reports.performance') }}" class="btn-hims btn-hims-primary w-100" style="justify-content:center">
                    <i class="bi bi-graph-up"></i> Open Performance Report
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="hims-card" style="height:100%;display:flex;flex-direction:column;justify-content:space-between">
            <div class="card-body" style="padding:24px">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.12);color:#059669;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:16px">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4 style="font-size:17px;font-weight:700;margin-bottom:8px">Credential Compliance & Expiry Report</h4>
                <p style="color:#6b7280;font-size:13px;line-height:1.6">
                    Audit of PRC medical licenses, BLS/ACLS certifications, JCI required credentials, and expiry horizons across all departments. Includes compliance charts and CSV export.
                </p>
                <div style="font-size:12px;color:#9ca3af;margin-top:12px">
                    <span style="font-weight:600;color:var(--hims-text-dark)">{{ $credentialsCount }}</span> recorded credentials covering <span style="font-weight:600;color:var(--hims-text-dark)">{{ $activeEmployees }}</span> active personnel.
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px;background:var(--hims-bg-subtle);border-top:1px solid var(--hims-border)">
                <a href="{{ route('reports.compliance') }}" class="btn-hims btn-hims-primary w-100" style="justify-content:center">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Open Compliance Report
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
