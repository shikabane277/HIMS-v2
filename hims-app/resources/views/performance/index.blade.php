@extends('layouts.hims')
@section('title','Performance Management')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance')

@php use App\Support\ReviewStatus; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Review Cycles</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage employee evaluation cycles and appraisal forms.</p>
    </div>
    <div class="d-flex gap-2">
        @can('manage-review-cycles')
            <a href="{{ route('performance.kpis.index') }}" class="btn-hims btn-hims-outline">
                <i class="bi bi-card-checklist"></i> KPI Library
            </a>
            <button type="button" class="btn-hims btn-hims-primary" data-modal-open="cycleCreateModal">
                <i class="bi bi-plus-circle"></i> New Cycle
            </button>
        @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon"><i class="bi bi-play-circle"></i></div><div class="stat-value">{{ $stats['active'] ?? 0 }}</div><div class="stat-label">Active Cycles</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="stat-value">{{ $stats['pending'] ?? 0 }}</div><div class="stat-label">Pending Reviews</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-diamond"></i></div><div class="stat-value">{{ $stats['pips'] ?? 0 }}</div><div class="stat-label">Active PIPs</div></div>
    </div>
</div>

{{-- Performance Analytics & Trends --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-pie-chart-fill text-primary-hims"></i> Rating Tier Distribution</h5>
                <span class="hims-badge green">All Evaluated Staff</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px;display:flex;align-items:center;justify-content:center">
                    <canvas id="performanceRatingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="hims-card" style="height:100%">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-graph-up text-primary-hims"></i> Cycle Performance Trends</h5>
                <span class="hims-badge blue">Review Volume & Score (1-5)</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:240px">
                    <canvas id="performanceCycleTrendChart"></canvas>
                </div>
            </div>
        </div>
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
                    <td data-label="Cycle Name"><strong>{{ $cycle->cycle_name }}</strong></td>
                    <td data-label="Type"><span class="hims-badge blue">{{ ucfirst(str_replace('_',' ',$cycle->cycle_type)) }}</span></td>
                    <td data-label="Period" style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($cycle->start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($cycle->end_date)->format('M d, Y') }}</td>
                    <td data-label="Status">
                        @php($cycleStatus = $cycle->effective_status ?? \App\Support\CycleStatus::of($cycle->status, $cycle->end_date))
                        <span class="hims-badge {{ \App\Support\CycleStatus::badgeClass($cycleStatus) }}">
                            <span class="status-dot {{ \App\Support\CycleStatus::isLive($cycleStatus) ? 'active' : 'inactive' }}"></span>
                            {{ \App\Support\CycleStatus::label($cycleStatus) }}
                        </span>
                    </td>
                    <td data-label="Reviews">{{ $cycle->reviews_count ?? 0 }} reviews</td>
                    <td data-label="Actions">
                        <a href="{{ route('performance.cycles.show', $cycle->cycle_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                        @can('manage-review-cycles')
                        <button type="button" class="btn-hims btn-hims-outline btn-sm"
                                data-modal-open="cycleModal" data-cycle-edit
                                data-action="{{ route('performance.cycles.update', $cycle->cycle_id) }}"
                                data-cycle_name="{{ $cycle->cycle_name }}"
                                data-cycle_type="{{ $cycle->cycle_type }}"
                                data-status="{{ $cycle->status }}"
                                data-start_date="{{ \Illuminate\Support\Str::substr((string) $cycle->start_date, 0, 10) }}"
                                data-end_date="{{ \Illuminate\Support\Str::substr((string) $cycle->end_date, 0, 10) }}">Edit</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:40px">No review cycles yet.@can('manage-review-cycles') <button type="button" class="hims-link-button text-primary-hims" data-modal-open="cycleCreateModal">Create your first cycle</button>.@endcan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-list-check"></i> Recent Reviews</h5>
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:11.5px;color:#9ca3af">Yours, the ones you wrote, and your direct reports'</span>
            <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost btn-sm">All Reviews</a>
        </div>
    </div>
    <div class="hims-sensitive-wrapper">
        <div class="hims-sensitive-records {{ ! \App\Support\RecordAccess::isUnlocked() ? 'is-blurred' : '' }}">
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr><th>Employee</th><th>Cycle</th><th>Type</th><th>Status</th><th>Score</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($reviews ?? [] as $review)
                        <tr>
                            <td data-label="Employee">
                                <div style="display:flex;align-items:center;gap:9px">
                                    <div style="width:32px;height:32px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--hims-primary-dark)">
                                        {{ strtoupper(substr($review->employee_first ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:13.5px">{{ ($review->employee_first ?? '') . ' ' . ($review->employee_last ?? '') }}</div>
                                        <div style="font-size:11px;color:#9ca3af">{{ $review->position_title ?? '' }}</div>
                                        <div style="font-size:11px;color:#6b7280">Reviewer: {{ trim($review->reviewer_name ?? '') ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Cycle" style="font-size:13px">{{ $review->cycle_name ?? '—' }}</td>
                            <td data-label="Type"><span class="hims-badge gray">{{ ucfirst($review->review_type ?? 'standard') }}</span></td>
                            <td data-label="Status">
                                <span class="hims-badge {{ ReviewStatus::badgeClass($review->effective_status) }}">{{ ReviewStatus::label($review->effective_status) }}</span>
                                @if($review->is_exception_review)
                                <span class="hims-badge yellow" style="margin-left:4px" title="{{ $review->exception_reason }}">
                                    <i class="bi bi-shield-exclamation"></i> {{ str_replace('_',' ',$review->exception_basis) }}
                                </span>
                                @endif
                            </td>
                            <td data-label="Score">
                                @if($review->overall_score)
                                    <span style="font-weight:700;color:{{ $review->overall_score >= 4 ? 'var(--hims-primary)' : ($review->overall_score < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                                        {{ number_format($review->overall_score,2) }}/5.00
                                    </span>
                                @else
                                    <span style="color:#9ca3af">—</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <a href="{{ route('performance.show', $review->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                                @if($review->can_score)
                                <a href="{{ route('performance.reviews.score', $review->review_id) }}" class="btn-hims btn-hims-outline btn-sm">Score</a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                            No reviews involve you yet. Reviews follow the reporting line.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(! \App\Support\RecordAccess::isUnlocked())
            @include('partials.record-access-overlay', ['slot' => 'Recent employee performance reviews and scores are protected. Enter the Record Access Password to unblur.'])
        @endif
    </div>
</div>

@can('manage-review-cycles')
    @include('performance.cycles._create-modal')
    @include('performance.cycles._edit-modal')
@endcan

@push('scripts')
<script>
(function () {
    const filter = document.getElementById('cycleFilter');
    if (!filter) return;

    filter.addEventListener('change', function () {
        const val = this.value.toLowerCase();
        const rows = document.querySelectorAll('#cycleFilter')
            ?.[0]?.closest('.hims-card')
            ?.querySelectorAll('tbody tr') ?? [];

        // Walk from the parent card's table
        const card = filter.closest('.hims-card');
        if (!card) return;
        const trs = card.querySelectorAll('tbody tr');

        trs.forEach(tr => {
            if (!val) { tr.style.display = ''; return; }
            const badge = tr.querySelector('.hims-badge');
            const status = badge ? badge.textContent.trim().toLowerCase() : '';
            tr.style.display = status === val ? '' : 'none';
        });
    });
})();

// Performance Charts Initialization
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    // 1. Rating Tiers Donut Chart
    const ratingCanvas = document.getElementById('performanceRatingChart');
    if (ratingCanvas) {
        const ratingData = {!! json_encode(array_values($ratingTiers ?? [])) !!};
        const ratingLabels = {!! json_encode(array_keys($ratingTiers ?? [])) !!};
        const hasData = ratingData.some(v => v > 0);

        new Chart(ratingCanvas, {
            type: 'doughnut',
            data: {
                labels: ratingLabels,
                datasets: [{
                    data: hasData ? ratingData : [4, 8, 12, 2],
                    backgroundColor: ['#059669', '#0284c7', '#d97706', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' reviews';
                            }
                        }
                    }
                },
                cutout: '62%'
            }
        });
    }

    // 2. Cycle Trends Multi-axis Chart
    const trendCanvas = document.getElementById('performanceCycleTrendChart');
    if (trendCanvas) {
        const cycleLabels = {!! json_encode($cycleTrendLabels ?? []) !!};
        const reviewCounts = {!! json_encode($cycleTrendReviews ?? []) !!};
        const avgScores = {!! json_encode($cycleTrendAvgScores ?? []) !!};

        new Chart(trendCanvas, {
            data: {
                labels: cycleLabels.length ? cycleLabels : ['Q1 Cycle', 'Q2 Cycle', 'Q3 Cycle', 'Annual Cycle'],
                datasets: [
                    {
                        type: 'line',
                        label: 'Avg Score (/5.00)',
                        data: cycleLabels.length ? avgScores : [3.85, 4.10, 3.92, 4.25],
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: false,
                        pointBackgroundColor: '#059669',
                        pointRadius: 4,
                        yAxisID: 'yScore'
                    },
                    {
                        type: 'bar',
                        label: 'Reviews Completed',
                        data: cycleLabels.length ? reviewCounts : [14, 22, 18, 30],
                        backgroundColor: 'rgba(2, 132, 199, 0.75)',
                        hoverBackgroundColor: '#0284c7',
                        borderRadius: 6,
                        barPercentage: 0.5,
                        yAxisID: 'yCount'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter' } }
                    }
                },
                scales: {
                    yScore: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: 5,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 11 } }
                    },
                    yCount: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
