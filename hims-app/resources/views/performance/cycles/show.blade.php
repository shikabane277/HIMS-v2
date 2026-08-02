@extends('layouts.hims')
@section('title',$cycle->cycle_name)
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Cycle')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $cycle->cycle_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ ucfirst(str_replace('_',' ',$cycle->cycle_type)) }} ·
            {{ \Carbon\Carbon::parse($cycle->start_date)->format('d M Y') }} – {{ \Carbon\Carbon::parse($cycle->end_date)->format('d M Y') }} ·
            <span class="hims-badge {{ $cycle->status === 'active' ? 'green' : ($cycle->status === 'closed' ? 'gray' : 'yellow') }}">{{ ucfirst($cycle->status) }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
        @can('manage-performance')
        <a href="{{ route('performance.reviews.create', ['cycle' => $cycle->cycle_id]) }}" class="btn-hims btn-hims-outline"><i class="bi bi-plus-lg"></i> Add Review</a>
        @endcan
        @can('manage-review-cycles')
        <a href="{{ route('performance.cycles.edit', $cycle->cycle_id) }}" class="btn-hims btn-hims-primary"><i class="bi bi-pencil"></i> Edit Cycle</a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="bi bi-people"></i></div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Reviews in Cycle</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-value">{{ $stats['completed'] }}</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value">{{ $stats['in_progress'] }}</div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value">{{ $stats['avg_score'] ?: '—' }}</div>
            <div class="stat-label">Average Score</div>
        </div>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-list-check"></i> Reviews</h5>
        <span style="font-size:11.5px;color:#9ca3af">Showing only employees you have access to</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Reviewer</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Overall</th>
                    <th style="width:140px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                <tr>
                    <td>
                        <strong>{{ $review->employee_name }}</strong>
                        <div style="font-size:11px;color:#9ca3af">{{ $review->position_title ?? '—' }}</div>
                    </td>
                    <td>{{ $review->department_name }}</td>
                    <td>{{ trim($review->reviewer_name) ?: '—' }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', $review->review_type ?? 'standard')) }}</td>
                    <td>
                        <span class="hims-badge {{ $review->status === 'completed' ? 'green' : ($review->status === 'draft' ? 'gray' : 'yellow') }}">
                            {{ ucfirst(str_replace('_',' ',$review->status)) }}
                        </span>
                    </td>
                    <td>
                        @if($review->overall_score !== null)
                            <span class="gap-chip {{ (float) $review->overall_score >= 3.5 ? 'positive' : 'negative' }}">
                                {{ number_format((float) $review->overall_score, 2) }}
                            </span>
                        @else
                            <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                        @can('manage-performance')
                        <a href="{{ route('performance.reviews.score', $review->review_id) }}" class="btn-hims btn-hims-outline btn-sm">Score</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="color:#9ca3af;padding:36px">
                    No reviews in this cycle yet.
                    @can('manage-performance')
                    <div class="mt-2"><a href="{{ route('performance.reviews.create', ['cycle' => $cycle->cycle_id]) }}" class="btn-hims btn-hims-primary btn-sm">Start the first review</a></div>
                    @endcan
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
