@extends('layouts.hims')
@section('title','Learning Management')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Course Catalogue &amp; Learning Pathways</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            What the hospital teaches, and the pathways it groups courses into. People are put on a
            course from <strong>Required Training</strong> — a course enrolment is always something
            somebody asked for, so it carries a deadline and shows up in the compliance figures.
        </p>
    </div>
    <div class="d-flex gap-2">
        {{-- Open to every role: the person owing the hours is the one who earns them. --}}
        <a href="{{ route('learning.cycles.mine') }}" class="btn-hims btn-hims-outline"><i class="bi bi-arrow-repeat"></i> My Renewal Cycles</a>
        @can('manage-learning')
        <button type="button" class="btn-hims btn-hims-outline" data-modal-open="pathwayCreateModal"><i class="bi bi-diagram-2"></i> New Pathway</button>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="courseCreateModal"><i class="bi bi-plus-circle"></i> New Course</button>
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
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📚</div><div class="stat-value">{{ $stats['total_courses'] ?? 0 }}</div><div class="stat-label">Total Courses</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $stats['completions_this_month'] ?? 0 }}</div><div class="stat-label">Completions This Month</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⏰</div><div class="stat-value">{{ $stats['avg_completion_rate'] ?? 0 }}%</div><div class="stat-label">Avg Completion Rate</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value">{{ $stats['certificates_issued'] ?? 0 }}</div><div class="stat-label">Certificates Issued</div></div></div>
</div>

{{-- The institutional half of Learning, in three numbers. Loaded only for
     supervisors and above — staff have no oversight tabs for it to link to, so
     for them it would be three figures leading nowhere. Each number is scoped
     the same way as the page behind it. --}}
@if($institutional)
<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-clipboard-check"></i> Hospital Requirements</h5>
        <span style="font-size:12px;color:#6b7280">{{ auth()->user()->seesWholeOrganisation() ? 'Whole hospital' : 'Your reporting line' }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3" style="font-size:13px">
            <div class="col-md-4">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Required Training Outstanding</div>
                <div style="font-size:22px;font-weight:700">{{ $institutional['outstanding'] }}</div>
                <a href="{{ route('learning.assignments.index') }}" class="text-primary-hims" style="font-size:12px">See what is required →</a>
            </div>
            <div class="col-md-4">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Past Their Deadline</div>
                <div style="font-size:22px;font-weight:700;color:{{ $institutional['overdue'] > 0 ? '#dc2626' : 'inherit' }}">{{ $institutional['overdue'] }}</div>
                <span style="font-size:12px;color:#9ca3af">Counted per person, per requirement.</span>
            </div>
            <div class="col-md-4">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Behind on Renewals</div>
                <div style="font-size:22px;font-weight:700;color:{{ $institutional['behind'] > 0 ? '#d97706' : 'inherit' }}">{{ $institutional['behind'] }}</div>
                <a href="{{ route('learning.renewals.index') }}" class="text-primary-hims" style="font-size:12px">See who is short →</a>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row g-3 mb-4">
    <!-- Course Grid -->
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-collection"></i> Course Catalogue</h5>
                {{-- House filter shape: the select submits its own form, so there
                     is no Apply button, and @selected reads the value back off the
                     request. Options are the categories actually in use — this
                     control was a hardcoded list wired to nothing. --}}
                <form method="GET" action="{{ route('learning.index') }}" style="margin:0">
                    <select name="category" class="hims-input hims-select" onchange="this.form.submit()"
                            style="width:160px;padding:6px 12px;font-size:13px" aria-label="Filter by category">
                        <option value="">All categories</option>
                        @foreach($categories as $option)
                        <option value="{{ $option }}" @selected($category === $option)>{{ ucfirst(str_replace('_',' ',$option)) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Course</th><th>Category</th><th>CPD Hours</th><th>Difficulty</th><th>Enrolled</th>
                            @can('manage-learning')<th class="text-end">Actions</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses ?? [] as $course)
                        <tr>
                            <td>
                                {{-- The catalogue no longer has a View button, so the
                                     title is the way through to the course page. --}}
                                <a href="{{ route('learning.courses.show', $course->course_id) }}"
                                   style="font-weight:600;color:var(--hims-text-dark);text-decoration:none">{{ $course->title }}</a>
                                <div style="font-size:11px;color:#9ca3af">{{ $course->course_code ?? '' }}{{ $course->course_code && $course->estimated_duration ? ' · ' : '' }}{{ $course->estimated_duration ? $course->estimated_duration.' mins' : '' }}</div>
                            </td>
                            <td><span class="hims-badge {{ $course->category === 'clinical' ? 'blue' : ($course->category === 'compliance' ? 'red' : 'green') }}">{{ ucfirst(str_replace('_',' ',$course->category)) }}</span></td>
                            <td><strong>{{ $course->cpd_hours }}</strong> hrs</td>
                            <td>
                                <span class="hims-badge {{ $course->difficulty_level === 'advanced' ? 'red' : ($course->difficulty_level === 'beginner' ? 'green' : 'yellow') }}">
                                    {{ ucfirst($course->difficulty_level) }}
                                </span>
                            </td>
                            <td>{{ $course->enrollments_count ?? 0 }}</td>
                            @can('manage-learning')
                            <td class="text-end">
                                {{-- One shared form, re-pointed from these data-* values. --}}
                                <button type="button" class="btn-hims btn-hims-ghost btn-sm"
                                        data-modal-open="courseEditModal"
                                        data-course-edit
                                        data-action="{{ route('learning.courses.update', $course->course_id) }}"
                                        data-title="{{ $course->title }}"
                                        data-course_code="{{ $course->course_code }}"
                                        data-category="{{ $course->category }}"
                                        data-cpd_hours="{{ (float) $course->cpd_hours }}"
                                        data-difficulty_level="{{ $course->difficulty_level }}"
                                        data-passing_score="{{ (float) $course->passing_score }}"
                                        data-estimated_duration="{{ $course->estimated_duration }}"
                                        data-is_mandatory="{{ $course->is_mandatory ? 1 : 0 }}"
                                        data-description="{{ $course->description }}"
                                        data-competencies="{{ $courseTags[$course->course_id] ?? '' }}">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()->can('manage-learning') ? 6 : 5 }}" class="text-center" style="color:#9ca3af;padding:32px">
                            @if($category)
                                No course in that category.
                                <a href="{{ route('learning.index') }}" class="text-primary-hims">Show all</a>.
                            @else
                                No courses yet.@can('manage-learning') <button type="button" class="hims-link-button text-primary-hims" data-modal-open="courseCreateModal">Create one</button>.@endcan
                            @endif
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Learning Pathways -->
    <div class="col-lg-4">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-signpost-split"></i> Pathways</h5>
                <a href="{{ route('learning.pathways.index') }}" class="btn-hims btn-hims-ghost btn-sm">All</a>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($pathways ?? [] as $pathway)
                <div style="padding:12px 14px;background:var(--hims-primary-pale);border:1px solid var(--hims-border);border-radius:8px;transition:.2s" onmouseover="this.style.background='var(--hims-primary-xlight)'" onmouseout="this.style.background='var(--hims-primary-pale)'">
                    <div style="font-weight:700;font-size:13.5px;color:var(--hims-text-dark)">{{ $pathway->pathway_name }}</div>
                    <div style="font-size:11.5px;color:#6b7280;margin-top:3px">{{ $pathway->total_cpd_hours ?? 0 }} CPD hrs · {{ $pathway->courses_count ?? 0 }} courses</div>
                    @if($pathway->is_mandatory)
                    <span class="hims-badge red mt-2" style="font-size:10px">Mandatory</span>
                    @endif
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:24px">No pathways created yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- CPD Tracker -->
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-award"></i> CPD Records &amp; Certificates</h5>
        <a href="{{ route('learning.cpd.index') }}" class="btn-hims btn-hims-ghost btn-sm">Full CPD Log</a>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Activity</th><th>Source</th><th>Hours</th><th>Date</th><th>Verified</th></tr></thead>
            <tbody>
                @forelse($cpd_records ?? [] as $cpd)
                <tr>
                    <td><strong>{{ $cpd->employee_name ?? '—' }}</strong></td>
                    <td>{{ $cpd->activity_name }}</td>
                    <td><span class="hims-badge {{ $cpd->source_type === 'course' ? 'blue' : 'gray' }}">{{ ucfirst($cpd->source_type) }}</span></td>
                    <td><strong>{{ $cpd->cpd_hours }}</strong> hrs</td>
                    <td>{{ \Carbon\Carbon::parse($cpd->date_earned)->format('M d, Y') }}</td>
                    <td>
                        <span class="hims-badge {{ $cpd->verified ? 'green' : 'yellow' }}">
                            {{ $cpd->verified ? '✓ Verified' : 'Pending' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">No CPD records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('manage-learning')
    @include('learning.courses._create-modal')
    @include('learning.courses._edit-modal')
    @include('learning.pathways._create-modal')
@endcan
@endsection
