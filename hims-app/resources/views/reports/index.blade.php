@extends('layouts.hims')
@section('title', 'Appraisal Overview — Reports & Analytics')
@section('page-title', 'Appraisal Overview')
@section('breadcrumb', 'HIMS / HR / Reports / Appraisal Overview')

@section('content')

{{-- Top Header Section matching Image 2 --}}
<div class="mb-4">
    <div style="background: linear-gradient(135deg, rgba(22,163,74,0.06) 0%, rgba(14,165,233,0.06) 100%); border: 1px solid var(--hims-border); border-radius: var(--hims-radius); padding: 24px 28px; margin-bottom: 20px;">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <span class="hims-badge green mb-2" style="font-size:11px;letter-spacing:0.04em">HR ANALYTICS SUITE</span>
                <h2 style="font-size:22px;font-weight:800;color:var(--hims-text-dark);margin:0 0 6px 0">Gain insights with the appraisal overview report</h2>
                <p style="color:var(--hims-gray);font-size:14px;margin:0;max-width:720px;line-height:1.5">
                    Get a comprehensive overview of your entire appraisal cycle with the appraisal reports. Track goal completions, self ratings, feedback distributions, and final calibrated evaluations across hospital staff.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('reports.compliance') }}" class="btn-hims btn-hims-outline" title="Credential Compliance Report">
                    <i class="bi bi-shield-check"></i> Credential Report
                </a>
                <button type="button" onclick="window.print()" class="btn-hims btn-hims-outline" title="Print this overview">
                    <i class="bi bi-printer"></i>
                </button>
                <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost" title="Refresh analytics" style="width:38px;height:38px;padding:0;justify-content:center">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- KPI Summary Stats row --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(244,63,94,0.12);color:#f43f5e"><i class="bi bi-bullseye"></i></div>
            <div class="stat-value">{{ $stats['total_reviews'] }}</div>
            <div class="stat-label">Total Appraisals Evaluated</div>
            <div class="stat-change up" style="font-size:11.5px;color:#6b7280;margin-top:6px">
                <i class="bi bi-check-circle-fill text-success"></i> Active in current scope
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,0.12);color:#10b981"><i class="bi bi-award-fill"></i></div>
            <div class="stat-value">{{ number_format($stats['avg_score'], 2) }} <span style="font-size:16px;font-weight:500;color:#9ca3af">/ 5.0</span></div>
            <div class="stat-label">Average Final Score</div>
            <div class="stat-change" style="font-size:11.5px;color:#10b981;margin-top:6px">
                <i class="bi bi-graph-up-arrow"></i> Calibrated hospital average
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(2,132,199,0.12);color:#0284c7"><i class="bi bi-clipboard2-check"></i></div>
            <div class="stat-value">{{ $stats['completed_count'] }}</div>
            <div class="stat-label">Completed Cycles</div>
            <div class="stat-change" style="font-size:11.5px;color:#6b7280;margin-top:6px">
                {{ $reviews->where('status', 'scoring')->count() }} pending supervisor sign-off
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(148,163,184,0.16);color:#475569"><i class="bi bi-repeat"></i></div>
            <div class="stat-value">{{ $stats['cycles_count'] }}</div>
            <div class="stat-label">Appraisal Cycles Configured</div>
            <div class="stat-change" style="font-size:11.5px;color:#6b7280;margin-top:6px">
                Across {{ $departments->count() }} hospital departments
            </div>
        </div>
    </div>
</div>

{{-- Main Appraisal Overview Card (Matching Image 2) --}}
<div class="hims-card mb-4">
    <div class="card-header" style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:18px 24px">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
            <div class="d-flex align-items-center gap-2">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(22,163,74,0.12);color:var(--hims-primary);display:flex;align-items:center;justify-content:center;font-size:16px">
                    <i class="bi bi-bar-chart-steps"></i>
                </div>
                <h4 style="font-size:18px;font-weight:800;color:var(--hims-text-dark);margin:0">Appraisal Overview</h4>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown" style="position:relative">
                    <button type="button" class="btn-hims btn-hims-ghost btn-sm" style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb">
                        Actions <i class="bi bi-chevron-down" style="font-size:10px;margin-left:4px"></i>
                    </button>
                </div>
                <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost btn-sm" style="width:32px;height:32px;padding:0;justify-content:center;border:1px solid #e5e7eb;border-radius:8px" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
                <button type="button" class="btn-hims btn-hims-ghost btn-sm" style="width:32px;height:32px;padding:0;justify-content:center;border:1px solid #e5e7eb;border-radius:8px" title="More options">
                    <i class="bi bi-three-dots"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Filter Bar styled like Image 2's pill filter toolbar --}}
    <div style="background:#fafafa;padding:14px 24px;border-bottom:1px solid #e5e7eb">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
            {{-- Appraisal Cycle --}}
            <div class="col-md-3 col-sm-6">
                <select name="cycle_id" class="hims-input hims-select" style="background:#ffffff;border-radius:10px;font-size:13px;padding:8px 12px" onchange="this.form.submit()">
                    <option value="all">All Appraisal Cycles</option>
                    @foreach($cycles as $c)
                        <option value="{{ $c->cycle_id }}" {{ $cycleId === $c->cycle_id ? 'selected' : '' }}>
                            {{ $c->cycle_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Employee Search --}}
            <div class="col-md-3 col-sm-6">
                <div style="position:relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search Employee..." class="hims-input" style="background:#ffffff;border-radius:10px;font-size:13px;padding:8px 12px 8px 34px">
                    <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px"></i>
                </div>
            </div>

            {{-- Department --}}
            <div class="col-md-3 col-sm-6">
                <select name="department_id" class="hims-input hims-select" style="background:#ffffff;border-radius:10px;font-size:13px;padding:8px 12px" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->department_id }}" {{ $departmentId == $d->department_id ? 'selected' : '' }}>
                            {{ $d->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Designation --}}
            <div class="col-md-2 col-sm-4">
                <select name="designation" class="hims-input hims-select" style="background:#ffffff;border-radius:10px;font-size:13px;padding:8px 12px" onchange="this.form.submit()">
                    <option value="">All Designations</option>
                    @foreach($designations as $des)
                        <option value="{{ $des }}" {{ $designation === $des ? 'selected' : '' }}>
                            {{ $des }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Submit / Reset --}}
            <div class="col-md-1 col-sm-2 d-flex gap-1">
                <button type="submit" class="btn-hims btn-hims-primary btn-sm w-100" style="padding:8px 0;justify-content:center;border-radius:8px" title="Apply filter">
                    <i class="bi bi-funnel-fill"></i>
                </button>
                @if($cycleId || $departmentId || $designation || $search)
                    <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost btn-sm" style="padding:8px 10px;justify-content:center;border-radius:8px;background:#e5e7eb" title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Interactive Multi-Bar Grouped Chart (Matching Image 2) --}}
    <div class="card-body" style="padding:28px 24px;background:#ffffff">
        <div style="position:relative;width:100%;height:320px">
            <canvas id="appraisalOverviewChart"></canvas>
        </div>

        {{-- Custom Legend matching Image 2 --}}
        <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap mt-3 pt-2" style="font-size:12.5px;font-weight:600;color:#374151">
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#f43f5e"></span>
                <span>Goal Score</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#0284c7"></span>
                <span>Self Score</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#10b981"></span>
                <span>Feedback Score</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#94a3b8"></span>
                <span>Final Score</span>
            </div>
        </div>
    </div>

    {{-- Appraisal Overview Table (Matching Image 2) --}}
    <div style="border-top:1px solid #e5e7eb">
        <div style="padding:14px 24px;background:#fafafa;display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:13px;font-weight:700;color:#374151">Evaluated Personnel & Scores Breakdown</span>
            <span style="font-size:12px;color:#6b7280;font-weight:500">{{ $reviews->count() }} Staff Record(s)</span>
        </div>

        <div class="table-responsive">
            <table class="hims-table" style="font-size:13px">
                <thead>
                    <tr style="background:#f8fafc">
                        <th class="text-center" style="width:45px">#</th>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Appraisal Cycle</th>
                        <th>Appraisal</th>
                        <th class="text-center">Feedback</th>
                        <th class="text-center">Avg Feedback</th>
                        <th class="text-center">Goal Score</th>
                        <th class="text-center">Self Score</th>
                        <th class="text-center">Final Score</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $index => $rev)
                    <tr>
                        <td class="text-center" data-label="#" style="color:#9ca3af;font-size:12px">{{ $index + 1 }}</td>
                        <td data-label="Employee ID">
                            <span style="font-family:monospace;font-weight:600;color:#0284c7;background:#f0f9ff;padding:2px 6px;border-radius:4px;border:1px solid #bae6fd">
                                {{ $rev->employee_code }}
                            </span>
                        </td>
                        <td data-label="Employee Name">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0">
                                    {{ substr($rev->first_name, 0, 1) }}{{ substr($rev->last_name, 0, 1) }}
                                </div>
                                <div>
                                    <a href="{{ route('reports.appraisal.show', $rev->review_id) }}" style="font-weight:700;color:var(--hims-text-dark);text-decoration:none">
                                        {{ $rev->first_name }} {{ $rev->last_name }}
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td data-label="Designation" style="color:#4b5563">{{ $rev->position_title ?: 'Clinical Staff' }}</td>
                        <td data-label="Department" style="color:#4b5563">{{ $rev->department_name }}</td>
                        <td data-label="Appraisal Cycle">
                            <span class="hims-badge gray" style="font-size:11px">{{ $rev->cycle_name }}</span>
                        </td>
                        <td data-label="Appraisal">
                            <span style="font-family:monospace;font-size:11.5px;color:#6b7280">
                                HR-APR-{{ substr($rev->review_id, 0, 8) }}
                            </span>
                        </td>
                        <td class="text-center" data-label="Feedback" style="font-weight:600;color:#4b5563">
                            {{ $rev->feedback_count ?? 1 }}
                        </td>
                        <td class="text-center" data-label="Avg Feedback">
                            @if($rev->competency_score)
                                <span style="font-weight:700;color:#10b981">{{ number_format($rev->competency_score, 2) }}</span>
                            @else
                                <span style="color:#9ca3af">0.00</span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Goal Score">
                            @if($rev->goal_score)
                                <span style="font-weight:700;color:#f43f5e">{{ number_format($rev->goal_score, 2) }}</span>
                            @else
                                <span style="color:#9ca3af">0.00</span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Self Score">
                            @if($rev->self_score)
                                <span style="font-weight:700;color:#0284c7">{{ number_format($rev->self_score, 2) }}</span>
                            @else
                                <span style="color:#9ca3af">0.00</span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Final Score">
                            @if($rev->final_score)
                                <span style="font-weight:800;color:#1f2937;background:#f3f4f6;padding:2px 8px;border-radius:6px">
                                    {{ number_format($rev->final_score, 2) }}
                                </span>
                            @else
                                <span style="color:#9ca3af">—</span>
                            @endif
                        </td>
                        <td class="text-end" data-label="Action">
                            <a href="{{ route('reports.appraisal.show', $rev->review_id) }}" class="btn-hims btn-hims-outline btn-sm" style="padding:4px 10px;font-size:12px">
                                <i class="bi bi-graph-up"></i> Details <i class="bi bi-chevron-right" style="font-size:10px"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="text-center" style="padding:48px;color:#9ca3af">
                            <i class="bi bi-folder2-open" style="font-size:32px;display:block;margin-bottom:8px"></i>
                            No appraisals found matching the selected filter criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('appraisalOverviewChart');
    if (!ctx) return;

    const labels = {!! json_encode($chartLabels) !!};
    const goalScores = {!! json_encode($chartGoalScores) !!};
    const selfScores = {!! json_encode($chartSelfScores) !!};
    const feedbackScores = {!! json_encode($chartFeedbackScores) !!};
    const finalScores = {!! json_encode($chartFinalScores) !!};

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['No Staff Evaluated'],
            datasets: [
                {
                    label: 'Goal Score',
                    data: goalScores.length > 0 ? goalScores : [0],
                    backgroundColor: '#f43f5e',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6,
                },
                {
                    label: 'Self Score',
                    data: selfScores.length > 0 ? selfScores : [0],
                    backgroundColor: '#0284c7',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6,
                },
                {
                    label: 'Feedback Score',
                    data: feedbackScores.length > 0 ? feedbackScores : [0],
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6,
                },
                {
                    label: 'Final Score',
                    data: finalScores.length > 0 ? finalScores : [0],
                    backgroundColor: '#94a3b8',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // We use custom legend matching Image 2
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleFont: { size: 13, weight: '700' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.dataset.label + ': ' + Number(context.raw).toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.6)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: { size: 12, weight: '600' },
                        color: '#4b5563'
                    }
                }
            }
        }
    });
});
</script>
@endpush

@endsection
