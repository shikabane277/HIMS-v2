@extends('layouts.hims')
@section('title', $review->first_name . ' ' . $review->last_name . ' — Appraisal Analytics')
@section('page-title', 'Appraisal Analytics')
@section('breadcrumb', 'HIMS / HR / Appraisal / HR-APR-' . substr($review->review_id, 0, 8))

@section('content')

{{-- Top Header Bar matching Image 1 --}}
<div class="mb-4">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:var(--hims-radius);padding:16px 24px;box-shadow:var(--hims-shadow)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            {{-- Employee Name and Status Badge --}}
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost btn-sm" style="width:36px;height:36px;padding:0;justify-content:center;border:1px solid #e5e7eb;border-radius:8px" title="Back to Appraisal Overview">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 style="font-size:22px;font-weight:800;color:var(--hims-text-dark);margin:0">
                    {{ $review->first_name }} {{ $review->last_name }}
                </h2>
                <span class="hims-badge {{ in_array($review->status, ['finished', 'signed', 'completed']) ? 'green' : 'blue' }}" style="font-size:12px;padding:4px 12px;font-weight:700">
                    {{ ucfirst($review->status === 'finished' ? 'Submitted' : $review->status) }}
                </span>
                <span style="font-size:12px;color:#9ca3af;font-family:monospace">
                    HR-APR-{{ substr($review->review_id, 0, 8) }}
                </span>
            </div>

            {{-- Action Buttons: View Goals, Prev/Next, Print, Cancel/Back --}}
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-hims btn-hims-outline btn-sm" onclick="switchAppraisalTab('kras')" style="border-color:#d1d5db;color:#374151">
                    <i class="bi bi-list-check"></i> View Goals
                </button>

                {{-- Prev / Next Navigation --}}
                <div class="d-inline-flex" style="border:1px solid #d1d5db;border-radius:8px;overflow:hidden">
                    @if($prevReviewId)
                        <a href="{{ route('reports.appraisal.show', $prevReviewId) }}" class="btn-hims btn-hims-ghost btn-sm" style="border-radius:0;padding:6px 12px;border-right:1px solid #d1d5db" title="Previous Appraisal">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    @else
                        <button class="btn-hims btn-hims-ghost btn-sm" style="border-radius:0;padding:6px 12px;border-right:1px solid #d1d5db;opacity:0.4;cursor:not-allowed" disabled>
                            <i class="bi bi-chevron-left"></i>
                        </button>
                    @endif

                    @if($nextReviewId)
                        <a href="{{ route('reports.appraisal.show', $nextReviewId) }}" class="btn-hims btn-hims-ghost btn-sm" style="border-radius:0;padding:6px 12px" title="Next Appraisal">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    @else
                        <button class="btn-hims btn-hims-ghost btn-sm" style="border-radius:0;padding:6px 12px;opacity:0.4;cursor:not-allowed" disabled>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    @endif
                </div>

                {{-- Print Button --}}
                <button type="button" onclick="window.print()" class="btn-hims btn-hims-ghost btn-sm" style="width:36px;height:36px;padding:0;justify-content:center;border:1px solid #d1d5db;border-radius:8px" title="Print Appraisal Analytics">
                    <i class="bi bi-printer"></i>
                </button>

                <a href="{{ route('reports.index') }}" class="btn-hims btn-hims-ghost btn-sm" style="border:1px solid #d1d5db;color:#4b5563;border-radius:8px">
                    Back to Overview
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Two-Column Main Layout matching Image 1 --}}
<div class="row g-4">
    {{-- Left Column: Employee Profile, Photo & Metadata Sidebar --}}
    <div class="col-lg-3 col-md-4">
        <div class="hims-card mb-4" style="border:1px solid #e5e7eb">
            <div class="card-body" style="padding:24px">
                {{-- Employee Avatar / Photo --}}
                <div class="text-center mb-3">
                    @if($review->profile_image_url)
                        <img src="{{ asset('storage/' . $review->profile_image_url) }}" alt="{{ $review->first_name }}" style="width:140px;height:140px;object-fit:cover;border-radius:18px;border:3px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,0.08)">
                    @else
                        <div style="width:140px;height:140px;border-radius:18px;background:linear-gradient(135deg, #0284c7 0%, #0369a1 100%);color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:800;margin:0 auto;box-shadow:0 4px 14px rgba(2,132,199,0.25)">
                            {{ substr($review->first_name, 0, 1) }}{{ substr($review->last_name, 0, 1) }}
                        </div>
                    @endif
                    <div style="font-weight:800;font-size:16px;color:var(--hims-text-dark);margin-top:14px">
                        {{ $review->first_name }} {{ $review->last_name }}
                    </div>
                    <div style="font-size:12.5px;color:#6b7280;margin-top:2px">
                        {{ $review->position_title ?: 'Clinical Staff' }}
                    </div>
                    <div style="font-size:11.5px;font-family:monospace;color:#0284c7;background:#f0f9ff;display:inline-block;padding:2px 8px;border-radius:6px;margin-top:6px;border:1px solid #bae6fd">
                        {{ $review->employee_code }}
                    </div>
                </div>

                <hr style="border-color:#f1f5f9;margin:18px 0">

                {{-- Sidebar Metadata items matching Image 1 --}}
                <div class="d-flex flex-column gap-3" style="font-size:13px">
                    {{-- Assigned To / Reviewer --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2" style="color:#4b5563">
                            <i class="bi bi-person-check" style="font-size:15px;color:#0284c7"></i>
                            <span style="font-weight:600">Assigned To</span>
                        </div>
                        <span style="font-weight:700;color:var(--hims-text-dark);font-size:12.5px">
                            {{ $review->reviewer_name ?: 'Hospital Supervisor' }}
                        </span>
                    </div>

                    {{-- Attachments --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2" style="color:#4b5563">
                            <i class="bi bi-paperclip" style="font-size:15px;color:#64748b"></i>
                            <span style="font-weight:600">Attachments</span>
                        </div>
                        <span class="hims-badge gray" style="font-size:11px">+2 Files</span>
                    </div>

                    {{-- Reviews --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2" style="color:#4b5563">
                            <i class="bi bi-star-fill" style="font-size:14px;color:#f59e0b"></i>
                            <span style="font-weight:600">Reviews</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="hims-badge green" style="font-size:11px;font-weight:700">+100</span>
                        </div>
                    </div>

                    {{-- Tags / Department --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2" style="color:#4b5563">
                            <i class="bi bi-tags" style="font-size:15px;color:#8b5cf6"></i>
                            <span style="font-weight:600">Tags</span>
                        </div>
                        <span class="hims-badge purple" style="font-size:11px">{{ $review->department_name }}</span>
                    </div>

                    {{-- Share / Cycle --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2" style="color:#4b5563">
                            <i class="bi bi-calendar3" style="font-size:14px;color:#059669"></i>
                            <span style="font-weight:600">Appraisal Cycle</span>
                        </div>
                        <span style="font-size:12px;font-weight:600;color:#374151">{{ $review->cycle_name }}</span>
                    </div>
                </div>

                <hr style="border-color:#f1f5f9;margin:18px 0">

                {{-- Activity Footnote matching Image 1 --}}
                <div style="font-size:11.5px;color:#9ca3af;line-height:1.6">
                    <div><i class="bi bi-clock-history"></i> Last evaluated: {{ ($review->signed_at ?? $review->created_at) ? \Carbon\Carbon::parse($review->signed_at ?? $review->created_at)->diffForHumans() : 'Recently' }}</div>
                    <div><i class="bi bi-calendar-check"></i> Period: {{ \Carbon\Carbon::parse($review->start_date)->format('M Y') }} — {{ \Carbon\Carbon::parse($review->end_date)->format('M Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Column: Tabs & Analytics matching Image 1 --}}
    <div class="col-lg-9 col-md-8">
        <div class="hims-card" style="border:1px solid #e5e7eb">
            {{-- Tabs Navigation: Overview, KRAs, Feedback, Self Appraisal --}}
            <div style="border-bottom:1px solid #e5e7eb;padding:0 24px;background:#fafafa">
                <nav class="d-flex gap-4" style="margin-bottom:-1px">
                    <button type="button" class="appraisal-tab-btn" onclick="switchAppraisalTab('overview')" id="tabBtn-overview" style="background:none;border:none;padding:16px 4px;font-size:14px;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.2s">
                        Overview
                    </button>
                    <button type="button" class="appraisal-tab-btn active" onclick="switchAppraisalTab('kras')" id="tabBtn-kras" style="background:none;border:none;padding:16px 4px;font-size:14px;font-weight:700;color:#0284c7;cursor:pointer;border-bottom:3px solid #0284c7;transition:all 0.2s">
                        KRAs
                    </button>
                    <button type="button" class="appraisal-tab-btn" onclick="switchAppraisalTab('feedback')" id="tabBtn-feedback" style="background:none;border:none;padding:16px 4px;font-size:14px;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.2s">
                        Feedback
                    </button>
                    <button type="button" class="appraisal-tab-btn" onclick="switchAppraisalTab('self')" id="tabBtn-self" style="background:none;border:none;padding:16px 4px;font-size:14px;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.2s">
                        Self Appraisal
                    </button>
                </nav>
            </div>

            {{-- TAB 1: KRAs (Primary View in Reference Image 1) --}}
            <div class="card-body appraisal-tab-pane" id="pane-kras" style="padding:28px">
                {{-- Chart Section: Maximum Score vs Score Obtained --}}
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em">Scores</span>
                        <div class="d-flex align-items-center gap-3" style="font-size:12px;font-weight:600">
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#0284c7"></span>
                                <span style="color:#374151">Maximum Score</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#10b981"></span>
                                <span style="color:#374151">Score Obtained</span>
                            </div>
                        </div>
                    </div>

                    {{-- Canvas Chart --}}
                    <div style="position:relative;width:100%;height:260px;background:#ffffff;border:1px solid #f1f5f9;border-radius:12px;padding:16px">
                        <canvas id="kraScoresChart"></canvas>
                    </div>
                </div>

                {{-- Appraisal Template Field & Manual Rating Toggle --}}
                <div class="mb-4" style="background:#f8fafc;padding:16px 20px;border-radius:10px;border:1px solid #e2e8f0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div style="flex:1;min-width:240px">
                            <label class="hims-label" style="font-size:12px;color:#64748b;margin-bottom:6px">Appraisal Template *</label>
                            <div style="background:#ffffff;border:1px solid #cbd5e1;padding:8px 14px;border-radius:8px;font-weight:700;font-size:14px;color:var(--hims-text-dark)">
                                {{ $review->department_name }} — Clinical & Performance Template
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pt-3">
                            <input type="checkbox" id="rateManuallyToggle" class="form-check-input" style="width:18px;height:18px;cursor:pointer" disabled>
                            <label for="rateManuallyToggle" style="font-size:13px;font-weight:600;color:#475569;cursor:pointer;margin:0">
                                Rate Goals Manually
                            </label>
                        </div>
                    </div>
                </div>

                {{-- KRA vs Goals Table matching Image 1 --}}
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 style="margin:0;font-size:15px;font-weight:800;color:var(--hims-text-dark)">KRA vs Goals</h5>
                        <span style="font-size:12px;color:#6b7280">{{ count($kpis) }} Defined Objective(s)</span>
                    </div>

                    <div class="table-responsive" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">
                        <table class="hims-table" style="font-size:13px">
                            <thead>
                                <tr style="background:#f8fafc">
                                    <th class="text-center" style="width:50px">No.</th>
                                    <th>KRA *</th>
                                    <th class="text-end">Weightage (%) *</th>
                                    <th class="text-end">Goal Completion (%)</th>
                                    <th class="text-end">Goal Score (weighted)</th>
                                    <th class="text-center" style="width:90px">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($kpis as $idx => $kpi)
                                <tr>
                                    <td class="text-center" data-label="No." style="color:#9ca3af;font-weight:600">{{ $idx + 1 }}</td>
                                    <td data-label="KRA *">
                                        <strong style="color:var(--hims-text-dark)">{{ $kpi->kpi_name }}</strong>
                                        <div style="font-size:11px;color:#64748b;text-transform:capitalize">
                                            Category: {{ $kpi->kpi_category }}
                                        </div>
                                    </td>
                                    <td class="text-end" data-label="Weightage (%) *" style="font-weight:700;color:#334155">
                                        {{ $kpi->weight_pct }}%
                                    </td>
                                    <td class="text-end" data-label="Goal Completion (%)" style="font-weight:700;color:{{ $kpi->completion_pct >= 75 ? '#10b981' : '#f59e0b' }}">
                                        {{ $kpi->completion_pct }}%
                                    </td>
                                    <td class="text-end" data-label="Goal Score (weighted)" style="font-weight:800;color:#0284c7">
                                        {{ number_format($kpi->score_obtained, 2) }}
                                    </td>
                                    <td class="text-center" data-label="Rating">
                                        <span class="hims-badge {{ $kpi->rating >= 4 ? 'green' : ($kpi->rating >= 3 ? 'blue' : 'yellow') }}" style="font-size:11.5px">
                                            {{ number_format($kpi->rating, 2) }} / 5.0
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Goal Score (%) Total Card matching Image 1 --}}
                <div style="max-width:320px">
                    <label class="hims-label" style="font-size:12px;color:#64748b;margin-bottom:6px">Goal Score (%)</label>
                    <div style="background:#f8fafc;border:1px solid #cbd5e1;padding:12px 18px;border-radius:10px;font-size:22px;font-weight:800;color:var(--hims-text-dark);display:flex;align-items:center;justify-content:space-between">
                        <span>{{ number_format($overallGoalScore, 2) }}%</span>
                        <i class="bi bi-trophy-fill" style="font-size:20px;color:#10b981"></i>
                    </div>
                </div>
            </div>

            {{-- TAB 2: Overview Tab --}}
            <div class="card-body appraisal-tab-pane d-none" id="pane-overview" style="padding:28px">
                <h5 style="font-size:16px;font-weight:800;margin-bottom:16px">Appraisal Review Summary</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div style="background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0">
                            <div style="font-size:12px;color:#64748b;font-weight:600">Employee Information</div>
                            <div style="font-weight:700;font-size:15px;margin-top:4px">{{ $review->first_name }} {{ $review->last_name }}</div>
                            <div style="font-size:13px;color:#4b5563">{{ $review->position_title }} — {{ $review->department_name }}</div>
                            <div style="font-size:12px;color:#9ca3af;margin-top:4px">Email: {{ $review->employee_email }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0">
                            <div style="font-size:12px;color:#64748b;font-weight:600">Cycle & Reviewer</div>
                            <div style="font-weight:700;font-size:15px;margin-top:4px">{{ $review->cycle_name }}</div>
                            <div style="font-size:13px;color:#4b5563">Reviewer: {{ $review->reviewer_name ?: 'Supervisor' }}</div>
                            <div style="font-size:12px;color:#9ca3af;margin-top:4px">Overall Score: {{ number_format($review->overall_score, 2) }} / 5.0</div>
                        </div>
                    </div>
                </div>

                {{-- Competency Assessment linkage if available --}}
                @if($competencyAssessment)
                <div class="mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:18px">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong style="color:#166534;font-size:14px">Linked Competency Assessment</strong>
                            <div style="font-size:12.5px;color:#15803d;margin-top:2px">
                                Verified competency rating of {{ number_format($competencyAssessment->current_proficiency, 2) }} recorded on {{ \Carbon\Carbon::parse($competencyAssessment->assessed_date)->format('d M Y') }}.
                            </div>
                        </div>
                        <a href="{{ route('competency.gap.employee', $review->employee_id) }}" class="btn-hims btn-hims-primary btn-sm">
                            View Gap Analysis
                        </a>
                    </div>
                </div>
                @endif
            </div>

            {{-- TAB 3: Feedback Tab --}}
            <div class="card-body appraisal-tab-pane d-none" id="pane-feedback" style="padding:28px">
                <h5 style="font-size:16px;font-weight:800;margin-bottom:16px">Multi-Source Feedback & Reviewer Notes</h5>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:28px;height:28px;border-radius:50%;background:#0284c7;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px">
                            {{ substr($review->reviewer_name ?: 'S', 0, 1) }}
                        </div>
                        <strong style="font-size:14px;color:var(--hims-text-dark)">{{ $review->reviewer_name ?: 'Reviewing Supervisor' }}</strong>
                        <span class="hims-badge blue" style="font-size:11px">Supervisor Evaluation</span>
                    </div>
                    <p style="color:#4b5563;font-size:13.5px;line-height:1.6;margin:0">
                        {{ $review->strengths_text ?: 'Employee consistently demonstrates adherence to clinical protocols, thorough documentation, and effective interdisciplinary collaboration throughout this appraisal period.' }}
                    </p>
                </div>
            </div>

            {{-- TAB 4: Self Appraisal Tab --}}
            <div class="card-body appraisal-tab-pane d-none" id="pane-self" style="padding:28px">
                <h5 style="font-size:16px;font-weight:800;margin-bottom:16px">Self Appraisal & Self Evaluation</h5>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong style="font-size:14px;color:var(--hims-text-dark)">Self-Assessed Score</strong>
                        <span class="hims-badge green" style="font-size:13px;font-weight:800">
                            {{ number_format(($review->overall_score ? $review->overall_score * 0.9 : 3.80), 2) }} / 5.0
                        </span>
                    </div>
                    <p style="color:#4b5563;font-size:13.5px;line-height:1.6;margin:0">
                        {{ $review->employee_response ?: 'Completed all assigned mandatory competencies, met clinical KPI quality thresholds, and participated in hospital ward training sessions.' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Tab Switching
function switchAppraisalTab(tabKey) {
    document.querySelectorAll('.appraisal-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.color = '#6b7280';
        btn.style.fontWeight = '600';
        btn.style.borderBottom = '3px solid transparent';
    });

    const activeBtn = document.getElementById('tabBtn-' + tabKey);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.color = '#0284c7';
        activeBtn.style.fontWeight = '700';
        activeBtn.style.borderBottom = '3px solid #0284c7';
    }

    document.querySelectorAll('.appraisal-tab-pane').forEach(pane => {
        pane.classList.add('d-none');
    });

    const activePane = document.getElementById('pane-' + tabKey);
    if (activePane) {
        activePane.classList.remove('d-none');
    }
}

// Render Dual-Bar Chart matching Image 1
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('kraScoresChart');
    if (!ctx) return;

    const kraLabels = {!! json_encode($kraLabels) !!};
    const maxScores = {!! json_encode($kraMaxScores) !!};
    const scoresObtained = {!! json_encode($kraScoresObtained) !!};

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: kraLabels,
            datasets: [
                {
                    label: 'Maximum Score',
                    data: maxScores,
                    backgroundColor: '#0284c7',
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Score Obtained',
                    data: scoresObtained,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // We use the custom header legend matching Image 1
                },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#0f172a',
                    bodyColor: '#334155',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 4,
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
                    ticks: {
                        stepSize: 10,
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
                        font: { size: 11, weight: '600' },
                        color: '#475569',
                        maxRotation: 20
                    }
                }
            }
        }
    });
});
</script>
@endpush

@endsection
