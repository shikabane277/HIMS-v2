@extends('layouts.hims')
@section('title',$course->title)
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Course')
@section('content')
@include('partials.learning-tabs')

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
        {{-- Informational only. Nobody enrols themselves in a course any more —
             every enrolment originates in Required Training — so this states a
             fact rather than offering an action. The tab strip covers "back". --}}
        @if($alreadyEnrolled)
            <span class="btn-hims btn-hims-outline" style="cursor:default"><i class="bi bi-check2-circle"></i> Enrolled</span>
        @endif
        @can('manage-compliance')
        <a href="{{ route('learning.assignments.index') }}" class="btn-hims btn-hims-primary">
            <i class="bi bi-person-plus"></i> Require This Course
        </a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3" data-auto-dismiss><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
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

                {{-- What this course is for. Tagged competencies are what make it
                     surface as a recommendation on the gap analysis screens. --}}
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--hims-border)">
                    <div class="hims-label" style="margin-bottom:7px">Remediates Competencies</div>
                    @forelse($competencies as $c)
                        <span class="hims-badge blue" style="margin:0 4px 4px 0;display:inline-block">
                            {{ $c->competency_name }}@if($c->competency_code) ({{ $c->competency_code }})@endif
                        </span>
                    @empty
                        <div style="font-size:12px;color:#b45309">
                            <i class="bi bi-exclamation-triangle"></i>
                            Untagged — this course will not appear in gap analysis recommendations.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @can('manage-learning')
    <div class="col-lg-4">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-tags"></i> Competency Tagging</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('learning.courses.competencies.update', $course->course_id) }}">
                    @csrf
                    <small style="display:block;color:#9ca3af;font-size:11.5px;margin-bottom:8px">
                        Selecting nothing clears every tag on this course.
                    </small>
                    @php $tagged = old('competencies', $competencies->pluck('competency_id')->all()); @endphp
                    @if($allCompetencies->isEmpty())
                        <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                            No competencies defined yet.
                        </div>
                    @else
                        <input type="search" class="hims-input hims-checklist-filter" data-checklist-filter="course_competencies"
                               placeholder="Filter competencies…" aria-label="Filter competencies">
                        <div class="hims-checklist" id="course_competencies">
                            @foreach($allCompetencies as $c)
                                <label>
                                    <input type="checkbox" name="competencies[]" value="{{ $c->competency_id }}"
                                           @checked(in_array($c->competency_id, $tagged, true))>
                                    <span>
                                        {{ $c->competency_name }}@if($c->competency_code) <span class="checklist-meta">({{ $c->competency_code }})</span>@endif
                                        @if($c->category_name)<br><span class="checklist-meta">{{ $c->category_name }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                            <div class="hims-checklist-empty" data-checklist-empty style="display:none">No competency matches that filter.</div>
                        </div>
                    @endif
                    <button type="submit" class="btn-hims btn-hims-primary mt-2" style="width:100%">
                        <i class="bi bi-check-circle"></i> Save Tags
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Full width once the tagging panel takes the other half of the top row. --}}
    <div class="{{ $allCompetencies->isNotEmpty() ? 'col-12' : 'col-lg-8' }}">
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
                            @can('record-completion')<th class="text-end"></th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $enrollment)
                        <tr>
                            <td data-label="Employee"><strong>{{ $enrollment->employee_name }}</strong></td>
                            <td data-label="Enrolled">
                                {{ $enrollment->enrollment_date ? \Carbon\Carbon::parse($enrollment->enrollment_date)->format('d M Y') : '—' }}
                                @if($enrollment->due_date)
                                <div style="font-size:11px;color:#9ca3af">due {{ \Carbon\Carbon::parse($enrollment->due_date)->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="hims-badge {{ $enrollment->status === 'completed' ? 'green' : ($enrollment->status === 'in_progress' ? 'yellow' : 'gray') }}">
                                    {{ ucfirst(str_replace('_',' ', (string) $enrollment->status)) }}
                                </span>
                                @if($enrollment->assignment_id)
                                <div style="font-size:11px;color:#b45309;margin-top:2px">Required</div>
                                @endif
                            </td>
                            <td data-label="Progress">
                                <div style="background:#eef2ff;border-radius:99px;height:8px;overflow:hidden">
                                    <div style="background:var(--hims-primary);height:100%;width:{{ max(0, min(100, (int) $enrollment->progress_pct)) }}%"></div>
                                </div>
                                <div style="font-size:11px;color:#6b7280;margin-top:3px">{{ (int) $enrollment->progress_pct }}%</div>
                            </td>
                            <td data-label="CPD Earned">{{ (float) $enrollment->cpd_hours_earned }}</td>
                            {{-- Completion is recorded about somebody, never by
                                 them, so this column is hidden from staff
                                 entirely rather than shown and refused. --}}
                            @can('record-completion')
                            <td data-label="Actions" class="text-end" style="white-space:nowrap">
                                @if($enrollment->status !== 'completed')
                                    <form method="POST" action="{{ route('learning.enrollments.complete', $enrollment->enrollment_id) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn-hims btn-hims-primary btn-sm">
                                            <i class="bi bi-check2"></i> Mark complete
                                        </button>
                                    </form>
                                @elseif(auth()->user()->can('manage-learning'))
                                    <form method="POST" action="{{ route('learning.enrollments.reopen', $enrollment->enrollment_id) }}" style="display:inline"
                                          onsubmit="return confirm('Withdraw this completion and remove its CPD credit?')">
                                        @csrf
                                        <button type="submit" class="btn-hims btn-hims-ghost btn-sm">Reopen</button>
                                    </form>
                                @endif
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()->can('record-completion') ? 6 : 5 }}" class="text-center" style="color:#9ca3af;padding:32px">
                            Nobody is enrolled in this course yet.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@can('manage-learning')
    @include('partials.checklist-js')
@endcan
@endsection
