@extends('layouts.hims')
@section('title', 'Performance Report')
@section('page-title', 'Performance Evaluation Report')
@section('breadcrumb', 'HIMS / Reports / Performance')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Performance Evaluation Report</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Cycle: <strong>{{ $selectedCycle->cycle_name ?? 'All Cycles' }}</strong>
            @if($selectedCycle)
                ({{ \Carbon\Carbon::parse($selectedCycle->start_date)->format('M Y') }} — {{ \Carbon\Carbon::parse($selectedCycle->end_date)->format('M Y') }})
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-outline">
            <i class="bi bi-arrow-left"></i> Reports Overview
        </a>
        <button type="button" onclick="window.print()" class="btn-hims btn-hims-outline">
            <i class="bi bi-printer"></i> Print / PDF
        </button>
        <a href="{{ route('reports.performance.export', ['cycle_id' => $selectedCycle->cycle_id ?? null]) }}" class="btn-hims btn-hims-primary">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
</div>

{{-- Cycle Selector --}}
<div class="hims-card mb-4 no-print">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="{{ route('reports.performance') }}" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="hims-label" style="margin-bottom:4px">Select Review Cycle</label>
                <select name="cycle_id" class="hims-input hims-select" onchange="this.form.submit()">
                    @foreach($cycles as $cycle)
                        <option value="{{ $cycle->cycle_id }}" {{ ($selectedCycle->cycle_id ?? null) === $cycle->cycle_id ? 'selected' : '' }}>
                            {{ $cycle->cycle_name }} ({{ ucfirst($cycle->cycle_type) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="hims-label" style="margin-bottom:4px">Average Overall Score</label>
                <div style="font-size:18px;font-weight:800;color:var(--hims-primary-dark)">
                    {{ number_format($avgScore, 2) }} / 5.00
                </div>
            </div>
            <div class="col-md-3">
                <label class="hims-label" style="margin-bottom:4px">Total Reviews</label>
                <div style="font-size:18px;font-weight:800;color:var(--hims-text-dark)">
                    {{ $reviews->count() }}
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Chart Card --}}
<div class="hims-card mb-4">
    <div class="card-header" style="padding:16px 20px">
        <h5 style="margin:0;font-size:15px"><i class="bi bi-bar-chart-fill text-primary-hims"></i> Overall Rating Distribution (1.0 to 5.0)</h5>
    </div>
    <div class="card-body" style="padding:20px">
        <div style="max-height:280px;position:relative">
            <canvas id="performanceChart" height="90"></canvas>
        </div>
    </div>
</div>

{{-- Review Details Table --}}
<div class="hims-card">
    <div class="card-header" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
        <h5 style="margin:0;font-size:15px">Review Evaluation Details</h5>
        <span style="font-size:12px;color:#6b7280">{{ $reviews->count() }} Record(s)</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Reviewer</th>
                    <th>Status</th>
                    <th>Overall Score</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $rev)
                <tr>
                    <td data-label="Employee">
                        <strong style="color:var(--hims-text-dark)">{{ $rev->first_name }} {{ $rev->last_name }}</strong>
                        <div style="font-size:11px;color:#9ca3af">{{ $rev->employee_code }}</div>
                    </td>
                    <td data-label="Department">{{ $rev->department_name }}</td>
                    <td data-label="Reviewer">{{ $rev->reviewer_name ?: '—' }}</td>
                    <td data-label="Status">
                        <span class="hims-badge {{ $rev->status === 'finished' ? 'green' : ($rev->status === 'scoring' ? 'blue' : 'gray') }}">
                            {{ ucfirst($rev->status) }}
                        </span>
                    </td>
                    <td data-label="Overall Score">
                        @if($rev->overall_score)
                            <strong style="font-size:14px;color:var(--hims-primary-dark)">{{ number_format($rev->overall_score, 2) }}</strong>
                            <span style="font-size:11px;color:#9ca3af">/ 5.0</span>
                        @else
                            <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                    <td data-label="Date" style="font-size:12px;color:#6b7280">
                        {{ $rev->submitted_at ? \Carbon\Carbon::parse($rev->submitted_at)->format('d M Y') : 'Pending' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding:40px;color:#9ca3af">
                        No reviews found for this cycle.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($distribution)) !!},
            datasets: [{
                label: 'Employees in Range',
                data: {!! json_encode(array_values($distribution)) !!},
                backgroundColor: [
                    'rgba(239, 68, 68, 0.7)',
                    'rgba(249, 115, 22, 0.7)',
                    'rgba(234, 179, 8, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)'
                ],
                borderColor: [
                    '#dc2626',
                    '#ea580c',
                    '#ca8a04',
                    '#2563eb',
                    '#059669'
                ],
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>
<style>
@media print {
    .no-print, .hims-sidebar, .hims-topbar, .btn-hims { display: none !important; }
    .hims-card { box-shadow: none !important; border: 1px solid #ccc !important; }
    body { background: #fff !important; }
}
</style>
@endpush

@endsection
