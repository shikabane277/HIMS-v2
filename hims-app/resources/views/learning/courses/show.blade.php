@extends('layouts.hims')
@section('title',$course->title)
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Course')
@section('content')

@php
    $alreadyEnrolled = $enrollments->contains('employee_id', Auth::user()->employee_id);
    $completedCount  = $enrollments->where('status','completed')->count();
    $avgProgress     = $enrollments->count() ? round($enrollments->avg('progress_pct')) : 0;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $course->title }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            @if($course->course_code)<span style="font-family:monospace">{{ $course->course_code }}</span> · @endif
            {{ ucfirst(str_replace('_',' ', (string) $course->category)) }}
            @if($course->is_mandatory)<span class="hims-badge red" style="margin-left:6px">Mandatory</span>@endif
            @if(! $course->is_active)<span class="hims-badge gray" style="margin-left:6px">Inactive</span>@endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('learning.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Catalogue</a>
        @if($alreadyEnrolled)
            <span class="btn-hims btn-hims-outline" style="cursor:default"><i class="bi bi-check2-circle"></i> Enrolled</span>
        @elseif($course->is_active)
            <form method="POST" action="{{ route('learning.enroll', $course->course_id) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-journal-plus"></i> Enrol Me</button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value">{{ (float) $course->cpd_hours }}</div>
            <div class="stat-label">CPD Hours</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-people"></i></div>
            <div class="stat-value">{{ $enrollments->count() }}</div>
            <div class="stat-label">Enrolled</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-patch-check"></i></div>
            <div class="stat-value">{{ $completedCount }}</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-bar-chart"></i></div>
            <div class="stat-value">{{ $avgProgress }}%</div>
            <div class="stat-label">Avg Progress</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-info-circle"></i> Course Details</h5></div>
            <div class="card-body">
                @if($course->description)
                <p style="font-size:13.5px;color:#374151;margin-bottom:16px">{{ $course->description }}</p>
                @endif
                <div style="display:grid;gap:12px;font-size:13px">
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Difficulty</div>
                        {{ ucfirst(str_replace('_',' ', (string) $course->difficulty_level)) }}
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Estimated Duration</div>
                        {{ $course->estimated_duration ? $course->estimated_duration.' minutes' : '—' }}
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Passing Score</div>
                        {{ (float) $course->passing_score }}%
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Max Retakes</div>
                        {{ $course->max_retakes }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="hims-card" style="height:100%">
            <div class="card-header">
                <h5><i class="bi bi-list-check"></i> Enrolled Employees</h5>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Enrolled</th>
                            <th>Status</th>
                            <th style="width:150px">Progress</th>
                            <th>CPD Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $enrollment)
                        <tr>
                            <td><strong>{{ $enrollment->employee_name }}</strong></td>
                            <td>
                                {{ $enrollment->enrollment_date ? \Carbon\Carbon::parse($enrollment->enrollment_date)->format('d M Y') : '—' }}
                                @if($enrollment->due_date)
                                <div style="font-size:11px;color:#9ca3af">due {{ \Carbon\Carbon::parse($enrollment->due_date)->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="hims-badge {{ $enrollment->status === 'completed' ? 'green' : ($enrollment->status === 'in_progress' ? 'yellow' : 'gray') }}">
                                    {{ ucfirst(str_replace('_',' ', (string) $enrollment->status)) }}
                                </span>
                            </td>
                            <td>
                                <div style="background:#eef2ff;border-radius:99px;height:8px;overflow:hidden">
                                    <div style="background:var(--hims-primary);height:100%;width:{{ max(0, min(100, (int) $enrollment->progress_pct)) }}%"></div>
                                </div>
                                <div style="font-size:11px;color:#6b7280;margin-top:3px">{{ (int) $enrollment->progress_pct }}%</div>
                            </td>
                            <td>{{ (float) $enrollment->cpd_hours_earned }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">
                            Nobody is enrolled in this course yet.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
