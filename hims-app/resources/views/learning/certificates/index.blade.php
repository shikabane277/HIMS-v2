@extends('layouts.hims')
@section('title','Certificates of Completion')
@section('page-title','Recognition & Awards')
@section('breadcrumb','HIMS / Recognition / Certificates')
@section('content')
@include('partials.recognition-tabs')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Training & Course Certificates</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Official credentials and CPD completion certificates issued to hospital staff.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost">
            <i class="bi bi-arrow-left"></i> Recognition Wall
        </a>
    </div>
</div>

<div class="hims-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-award-fill"></i> Issued Certificates ({{ $certificates->total() }})</h5>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Certificate Code</th>
                    <th>Staff Member</th>
                    <th>Department</th>
                    <th>Course Title</th>
                    <th>CPD Hours</th>
                    <th>Issue Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $cert)
                <tr>
                    <td data-label="Certificate Code">
                        <strong style="font-family:monospace;color:#2563eb">{{ $cert->certificate_code }}</strong>
                    </td>
                    <td data-label="Staff Member">
                        <strong>{{ $cert->first_name }} {{ $cert->last_name }}</strong>
                        <div style="font-size:11.5px;color:#6b7280;font-family:monospace">{{ $cert->employee_code }}</div>
                    </td>
                    <td data-label="Department">{{ $cert->department_name }}</td>
                    <td data-label="Course Title">
                        <strong>{{ $cert->course_title }}</strong>
                    </td>
                    <td data-label="CPD Hours">
                        <span class="hims-badge blue">{{ $cert->cpd_hours ?? 0 }} hrs</span>
                    </td>
                    <td data-label="Issue Date" style="font-size:12.5px;color:#6b7280">
                        {{ \Carbon\Carbon::parse($cert->issued_date)->format('M d, Y') }}
                    </td>
                    <td data-label="Actions">
                        <a href="{{ route('learning.certificates.show', $cert->certificate_code) }}" target="_blank" class="btn-hims btn-hims-outline btn-sm">
                            <i class="bi bi-eye"></i> View / Print
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center" style="color:#9ca3af;padding:48px">
                        <div style="font-size:36px;margin-bottom:10px">📜</div>
                        No certificates issued yet. Certificates are automatically generated when course enrollments are marked completed.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($certificates->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">
        {{ $certificates->links() }}
    </div>
    @endif
</div>
@endsection
