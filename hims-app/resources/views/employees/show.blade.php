@extends('layouts.hims')
@section('title','Employee Profile')
@section('page-title','Employee Profile')
@section('breadcrumb','HIMS / Employees / Profile')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost">
        <i class="bi bi-arrow-left"></i> Back to Employees
    </a>
</div>

<div class="row g-3">
    {{-- Profile Card --}}
    <div class="col-lg-4">
        <div class="hims-card mb-3">
            <div style="padding:28px 24px;text-align:center;background:linear-gradient(135deg,var(--hims-primary-pale) 0%,var(--hims-primary-xlight) 100%);border-bottom:1px solid var(--hims-border)">
                <div style="width:72px;height:72px;background:var(--hims-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;margin:0 auto 14px">
                    {{ strtoupper(substr($employee->first_name,0,1)) }}
                </div>
                <div style="font-size:18px;font-weight:800;color:var(--hims-text-dark)">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                <div style="font-size:13px;color:#6b7280;margin-top:3px">{{ $employee->position_title ?? '—' }}</div>
                <div class="mt-2">
                    <span class="hims-badge green">{{ $employee->department_name }}</span>
                    <span class="hims-badge {{ $employee->employment_status === 'active' ? 'green' : 'gray' }} ms-1">
                        {{ ucfirst($employee->employment_status) }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <table style="width:100%;font-size:13px">
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280;width:40%">Code</td>
                        <td style="padding:9px 0;font-family:monospace;font-weight:600">{{ $employee->employee_code }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Email</td>
                        <td style="padding:9px 0">{{ $employee->email }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:9px 0;color:#6b7280">Role</td>
                        <td style="padding:9px 0">{{ $employee->role_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0;color:#6b7280">Hired</td>
                        <td style="padding:9px 0">{{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Right column --}}
    <div class="col-lg-8">
        {{-- Performance Reviews --}}
        <div class="hims-card mb-3">
            <div class="card-header">
                <h5><i class="bi bi-clipboard-check"></i> Performance Reviews</h5>
                <span class="hims-badge gray">{{ count($reviews) }} records</span>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Cycle</th><th>Status</th><th>Score</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($reviews as $r)
                        <tr>
                            <td>{{ $r->cycle_id ?? '—' }}</td>
                            <td><span class="hims-badge {{ $r->status === 'approved' ? 'green' : 'yellow' }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span></td>
                            <td>{{ $r->overall_score ? number_format($r->overall_score,2).'/5.00' : '—' }}</td>
                            <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($r->created_at)->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">No reviews yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Enrollments --}}
        <div class="hims-card mb-3">
            <div class="card-header">
                <h5><i class="bi bi-book"></i> Course Enrollments</h5>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Course</th><th>CPD Hrs</th><th>Progress</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($enrollments as $en)
                        <tr>
                            <td><strong>{{ $en->title }}</strong></td>
                            <td>{{ $en->cpd_hours }}</td>
                            <td>
                                <div style="min-width:80px">
                                    <div style="font-size:11px;color:#6b7280;margin-bottom:3px">{{ $en->progress_pct }}%</div>
                                    <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $en->progress_pct }}%"></div></div>
                                </div>
                            </td>
                            <td><span class="hims-badge {{ $en->status === 'completed' ? 'green' : 'yellow' }}">{{ ucfirst($en->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Not enrolled in any courses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Credentials --}}
        <div class="hims-card mb-3">
            <div class="card-header">
                <h5><i class="bi bi-patch-check-fill"></i> Credentials & Licenses</h5>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Type</th><th>Number</th><th>Issued By</th><th>Expiry</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($credentials as $cred)
                        @php
                            $expired = $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->isPast();
                            $expiringSoon = !$expired && $cred->expiry_date && \Carbon\Carbon::parse($cred->expiry_date)->diffInDays(now()) <= 30;
                        @endphp
                        <tr>
                            <td><strong>{{ $cred->credential_type }}</strong></td>
                            <td style="font-family:monospace;font-size:12.5px">{{ $cred->credential_number ?? '—' }}</td>
                            <td style="font-size:12.5px">{{ $cred->issuing_body ?? '—' }}</td>
                            <td style="font-size:12.5px;{{ $expired ? 'color:var(--hims-danger);font-weight:700' : ($expiringSoon ? 'color:var(--hims-warning);font-weight:600' : '') }}">
                                {{ $cred->expiry_date ? \Carbon\Carbon::parse($cred->expiry_date)->format('M d, Y') : '—' }}
                            </td>
                            <td>
                                <span class="hims-badge {{ $expired ? 'red' : ($expiringSoon ? 'yellow' : 'green') }}">
                                    {{ $expired ? 'Expired' : ($expiringSoon ? 'Expiring Soon' : 'Valid') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">No credentials on file.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recognition --}}
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-star-fill"></i> Recognition Received</h5>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($recognitions as $post)
                <div class="recognition-post">
                    <div style="font-size:13px;line-height:1.7">{{ $post->message }}</div>
                    <div style="font-size:11.5px;color:#9ca3af;margin-top:6px">
                        {{ \Carbon\Carbon::parse($post->created_at)->format('M d, Y') }}
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:24px">No recognitions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
