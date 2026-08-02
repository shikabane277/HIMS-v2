@extends('layouts.hims')
@section('title',$candidate->employee_name)
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Candidate')
@section('content')

@php
    $readinessLabels = [
        'ready_now'  => 'Ready Now',
        '1_2_years'  => 'Ready in 1–2 Years',
        '2_5_years'  => 'Ready in 2–5 Years',
        'long_term'  => 'Long Term',
    ];
    $nineBoxLabels = [
        'star' => 'Star', 'high' => 'High Performer', 'solid' => 'Solid Performer',
        'potential' => 'High Potential', 'core' => 'Core Player', 'avg' => 'Average Performer',
        'diamond' => 'Rough Diamond', 'inconsist' => 'Inconsistent', 'under' => 'Underperformer',
    ];
    $readiness = (string) $candidate->readiness_level;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $candidate->employee_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $candidate->current_title ?? 'No current title' }}
            <i class="bi bi-arrow-right" style="margin:0 4px"></i>
            <strong style="color:var(--hims-text-dark)">{{ $candidate->target_title }}</strong>
        </p>
    </div>
    <div class="d-flex gap-2">
        @can('manage-succession')
        <a href="{{ route('succession.candidates.edit', $candidate->candidate_id) }}" class="btn-hims btn-hims-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        @endcan
        <a href="{{ route('succession.positions.show', $candidate->position_id) }}" class="btn-hims btn-hims-ghost">
            <i class="bi bi-arrow-left"></i> Back to Position
        </a>
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="hims-alert error mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul style="margin:4px 0 0 18px;padding:0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-speedometer2"></i></div>
            <div class="stat-value">{{ $candidate->performance_score }}<span style="font-size:14px;color:#9ca3af">/5</span></div>
            <div class="stat-label">Performance</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value">{{ $candidate->potential_score }}<span style="font-size:14px;color:#9ca3af">/5</span></div>
            <div class="stat-label">Potential</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-grid-3x3"></i></div>
            <div class="stat-value" style="font-size:16px">{{ $nineBoxLabels[$candidate->nine_box_label] ?? ($candidate->nine_box_label ?? '—') }}</div>
            <div class="stat-label">9-Box Placement</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value" style="font-size:16px">{{ $readinessLabels[$readiness] ?? ucfirst(str_replace('_',' ', $readiness)) }}</div>
            <div class="stat-label">Readiness</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-info-circle"></i> Nomination</h5></div>
            <div class="card-body">
                <div style="display:grid;gap:12px;font-size:13px">
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Status</div>
                        <span class="hims-badge {{ $candidate->status === 'approved' ? 'green' : 'gray' }}">
                            {{ ucfirst(str_replace('_',' ', (string) $candidate->status)) }}
                        </span>
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Nominated</div>
                        {{ $candidate->nominated_at ? \Carbon\Carbon::parse($candidate->nominated_at)->format('d M Y') : '—' }}
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Reviewed</div>
                        {{ $candidate->reviewed_at ? \Carbon\Carbon::parse($candidate->reviewed_at)->format('d M Y') : 'Not yet reviewed' }}
                    </div>
                    <div>
                        <div class="hims-label" style="margin-bottom:2px">Approved</div>
                        {{ $candidate->approved_at ? \Carbon\Carbon::parse($candidate->approved_at)->format('d M Y') : 'Not yet approved' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @php
            $doneCount = $dev_paths->where('status','completed')->count();
            $devPct    = $dev_paths->count() ? (int) round(100 * $doneCount / $dev_paths->count()) : 0;
        @endphp
        <div class="hims-card" style="height:100%">
            <div class="card-header">
                <h5><i class="bi bi-signpost-split"></i> Leadership Development Path</h5>
                <span style="font-size:11.5px;color:#9ca3af">
                    {{ $doneCount }}/{{ $dev_paths->count() }} complete &middot; {{ $devPct }}%
                </span>
            </div>
            @if($dev_paths->count())
            <div style="padding:12px 16px 0">
                <div class="hims-progress"><div class="hims-progress-bar" style="width:{{ $devPct }}%"></div></div>
            </div>
            @endif
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Milestone</th>
                            <th>Type</th>
                            <th>Target</th>
                            <th>Status</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dev_paths as $path)
                        <tr>
                            <td>
                                <strong>{{ $path->milestone_title }}</strong>
                                @if($path->description)
                                <div style="font-size:11px;color:#6b7280;margin-top:3px;max-width:320px">{{ $path->description }}</div>
                                @endif
                            </td>
                            <td>{{ $path->milestone_type ? ucfirst(str_replace('_',' ', $path->milestone_type)) : '—' }}</td>
                            <td>
                                {{ $path->target_date ? \Carbon\Carbon::parse($path->target_date)->format('d M Y') : '—' }}
                                @if($path->completed_date)
                                <div style="font-size:11px;color:#16a34a">done {{ \Carbon\Carbon::parse($path->completed_date)->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td>
                                {{-- Inline status change: one select that submits itself. --}}
                                <form method="POST" action="{{ route('succession.milestones.update', [$candidate->candidate_id, $path->path_id]) }}">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" onchange="this.form.submit()"
                                            class="hims-input hims-select"
                                            style="width:132px;padding:5px 10px;font-size:12px">
                                        @foreach(['not_started'=>'Not started','in_progress'=>'In progress','completed'=>'Completed'] as $val => $text)
                                            <option value="{{ $val }}" @selected($path->status === $val)>{{ $text }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('succession.milestones.destroy', [$candidate->candidate_id, $path->path_id]) }}"
                                      onsubmit="return confirm('Remove this milestone?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-hims btn-hims-ghost btn-sm" title="Remove milestone">
                                        <i class="bi bi-trash" style="color:var(--hims-danger)"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">
                            No development milestones recorded for this candidate yet.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="hims-card mt-3">
    <div class="card-header"><h5><i class="bi bi-plus-circle"></i> Add Development Milestone</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('succession.milestones.store', $candidate->candidate_id) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="hims-label">Milestone Title *</label>
                    <input type="text" name="milestone_title" class="hims-input" required
                           value="{{ old('milestone_title') }}" maxlength="200"
                           placeholder="e.g. Complete Advanced Leadership course">
                </div>
                <div class="col-md-4">
                    <label class="hims-label">Type</label>
                    <select name="milestone_type" class="hims-input hims-select">
                        <option value="">— Select —</option>
                        @foreach(['course'=>'Course','assignment'=>'Assignment','mentoring'=>'Mentoring','rotation'=>'Rotation','certification'=>'Certification','project'=>'Project'] as $val => $text)
                            <option value="{{ $val }}" @selected(old('milestone_type') === $val)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="hims-label">Target Date</label>
                    <input type="date" name="target_date" class="hims-input" value="{{ old('target_date') }}">
                </div>
                <div class="col-12">
                    <label class="hims-label">Description</label>
                    <textarea name="description" class="hims-input" rows="2" maxlength="1000"
                              placeholder="Optional detail about what this milestone involves">{{ old('description') }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn-hims btn-hims-primary">
                        <i class="bi bi-plus-lg"></i> Add Milestone
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
