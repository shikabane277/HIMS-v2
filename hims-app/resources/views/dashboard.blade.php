@extends('layouts.hims')
@section('title','Dashboard')
@section('page-title', $heading ?? 'Dashboard')
@section('breadcrumb','HIMS / Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $heading ?? 'Dashboard' }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">{{ $subheading ?? '' }}</p>
    </div>
    <span class="hims-badge blue"><i class="bi bi-eye"></i> Scope: {{ $scope ?? 'Whole organisation' }}</span>
</div>

@if(($role ?? 'staff') === 'staff')
    @include('dashboard.partials.staff')
@elseif(($role ?? '') === 'supervisor')
    @include('dashboard.partials.supervisor')
@else
    @include('dashboard.partials.organisation')
@endif
@endsection

@push('scripts')
@if(isset($review_trends) && count($review_trends) > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trendCtx = document.getElementById('dashboardTrendChart');
    if (trendCtx) {
        const trendData = @json($review_trends);
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(d => d.month),
                datasets: [
                    {
                        label: 'Average Score',
                        data: trendData.map(d => d.avg_score),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true,
                        tension: 0.35,
                        yAxisID: 'y',
                        pointBackgroundColor: '#2563eb',
                        pointRadius: 4
                    },
                    {
                        label: 'Reviews Completed',
                        data: trendData.map(d => d.count),
                        type: 'bar',
                        backgroundColor: 'rgba(148, 163, 184, 0.35)',
                        borderRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 5,
                        title: { display: true, text: 'Avg Score (1-5)', font: { size: 11 } }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Reviews Count', font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>
@endif
@endpush
