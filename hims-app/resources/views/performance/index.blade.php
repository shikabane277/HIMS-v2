@extends('layouts.hims')
@section('title','Performance Management')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Review Cycles</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage employee evaluation cycles and appraisal forms.</p>
    </div>
    <a href="{{ route('performance.cycles.create') }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-plus-circle"></i> New Cycle
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon">🟢</div><div class="stat-value">{{ $stats['active'] ?? 0 }}</div><div class="stat-label">Active Cycles</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value">{{ $stats['pending'] ?? 0 }}</div><div class="stat-label">Pending Reviews</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon">🚨</div><div class="stat-value">{{ $stats['pips'] ?? 0 }}</div><div class="stat-label">Active PIPs</div></div>
    </div>
</div>

<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-calendar3"></i> Review Cycles</h5>
        <div class="d-flex gap-2">
            <select class="hims-input hims-select" style="width:140px;padding:6px 12px;font-size:13px" id="cycleFilter">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="planned">Planned</option>
                <option value="closed">Closed</option>
            </select>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Cycle Name</th><th>Type</th><th>Period</th><th>Status</th><th>Reviews</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($cycles ?? [] as $cycle)
                <tr>
                    <td><strong>{{ $cycle->cycle_name }}</strong></td>
                    <td><span class="hims-badge blue">{{ ucfirst(str_replace('_',' ',$cycle->cycle_type)) }}</span></td>
                    <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($cycle->start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($cycle->end_date)->format('M d, Y') }}</td>
                    <td>
                        <span class="hims-badge {{ $cycle->status === 'active' ? 'green' : ($cycle->status === 'closed' ? 'gray' : 'yellow') }}">
                            <span class="status-dot {{ $cycle->status === 'active' ? 'active' : 'inactive' }}"></span>
                            {{ ucfirst($cycle->status) }}
                        </span>
                    </td>
                    <td>{{ $cycle->reviews_count ?? 0 }} reviews</td>
                    <td>
                        <a href="{{ route('performance.cycles.show', $cycle->cycle_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                        <a href="{{ route('performance.cycles.edit', $cycle->cycle_id) }}" class="btn-hims btn-hims-outline btn-sm">Edit</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:40px">No review cycles yet. <a href="{{ route('performance.cycles.create') }}" class="text-primary-hims">Create your first cycle</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-list-check"></i> Recent Reviews</h5>
        <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost btn-sm">All Reviews</a>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Employee</th><th>Cycle</th><th>Type</th><th>Status</th><th>Score</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($reviews ?? [] as $review)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <div style="width:32px;height:32px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--hims-primary-dark)">
                                {{ strtoupper(substr($review->employee_first ?? 'U', 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px">{{ ($review->employee_first ?? '') . ' ' . ($review->employee_last ?? '') }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $review->position_title ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px">{{ $review->cycle_name ?? '—' }}</td>
                    <td><span class="hims-badge gray">{{ ucfirst($review->review_type ?? 'standard') }}</span></td>
                    <td>
                        @php
                            $statusColor = match($review->status ?? '') {
                                'approved','archived' => 'green',
                                'draft','self_assessment' => 'gray',
                                'ai_audit','pending_approval' => 'blue',
                                default => 'yellow'
                            };
                        @endphp
                        <span class="hims-badge {{ $statusColor }}">{{ ucfirst(str_replace('_',' ',$review->status ?? 'draft')) }}</span>
                    </td>
                    <td>
                        @if($review->overall_score)
                            <span style="font-weight:700;color:{{ $review->overall_score >= 4 ? 'var(--hims-primary)' : ($review->overall_score < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                                {{ number_format($review->overall_score,2) }}/5.00
                            </span>
                        @else
                            <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">No reviews found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
