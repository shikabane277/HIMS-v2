@extends('layouts.hims')
@section('title','Learning Management')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Course Catalog & Learning Pathways</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage hospital courses, CPD tracking, and employee enrollments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('learning.pathways.create') }}" class="btn-hims btn-hims-outline"><i class="bi bi-diagram-2"></i> New Pathway</a>
        <a href="{{ route('learning.courses.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-plus-circle"></i> New Course</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">📚</div><div class="stat-value">{{ $stats['total_courses'] ?? 0 }}</div><div class="stat-label">Total Courses</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value">{{ $stats['completions_this_month'] ?? 0 }}</div><div class="stat-label">Completions This Month</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">⏰</div><div class="stat-value">{{ $stats['avg_completion_rate'] ?? 0 }}%</div><div class="stat-label">Avg Completion Rate</div></div></div>
    <div class="col-sm-3"><div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value">{{ $stats['certificates_issued'] ?? 0 }}</div><div class="stat-label">Certificates Issued</div></div></div>
</div>

<div class="row g-3 mb-4">
    <!-- Course Grid -->
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-collection"></i> Course Catalog</h5>
                <div class="d-flex gap-2">
                    <select class="hims-input hims-select" style="width:130px;padding:6px 12px;font-size:13px">
                        <option>All Categories</option>
                        <option>Clinical</option>
                        <option>Compliance</option>
                        <option>Soft Skills</option>
                    </select>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Course</th><th>Category</th><th>CPD Hours</th><th>Difficulty</th><th>Enrolled</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($courses ?? [] as $course)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $course->title }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $course->course_code ?? '' }} · {{ $course->estimated_duration ? $course->estimated_duration.' mins' : '' }}</div>
                            </td>
                            <td><span class="hims-badge {{ $course->category === 'clinical' ? 'blue' : ($course->category === 'compliance' ? 'red' : 'green') }}">{{ ucfirst($course->category) }}</span></td>
                            <td><strong>{{ $course->cpd_hours }}</strong> hrs</td>
                            <td>
                                <span class="hims-badge {{ $course->difficulty_level === 'advanced' ? 'red' : ($course->difficulty_level === 'beginner' ? 'green' : 'yellow') }}">
                                    {{ ucfirst($course->difficulty_level) }}
                                </span>
                            </td>
                            <td>{{ $course->enrollments_count ?? 0 }}</td>
                            <td>
                                <a href="{{ route('learning.courses.show', $course->course_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                                <a href="{{ route('learning.enroll', $course->course_id) }}" class="btn-hims btn-hims-primary btn-sm">Enroll</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">No courses yet. <a href="{{ route('learning.courses.create') }}" class="text-primary-hims">Create one</a>.</td></tr>
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
        <h5><i class="bi bi-award"></i> CPD Records & Certificates</h5>
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
@endsection
