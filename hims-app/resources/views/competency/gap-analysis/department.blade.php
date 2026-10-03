@extends('layouts.hims')
@section('title','Department Gap Analysis')
@section('page-title','AI-Driven Competency Gap Analysis')
@section('breadcrumb','HIMS / Competency / Gap Analysis / Department')

@section('content')
@php $ai = $analysis['ai']; @endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">
            {{ $analysis['department']->name ?? 'Whole Organisation' }} — Workforce Gap Analysis
        </h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $analysis['headcount'] }} active staff · generated {{ $analysis['generated_at']->diffForHumans() }}
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @if(!empty($departments) && $departments->isNotEmpty())
        <div class="d-flex align-items-center gap-1" style="position:relative">
            <label class="hims-label mb-0 text-nowrap" style="font-size:12px;color:#6b7280" for="switchDeptInput">Switch Department:</label>
            <div style="position:relative">
                <i class="bi bi-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:11px;pointer-events:none"></i>
                <input type="text"
                       id="switchDeptInput"
                       class="hims-input"
                       placeholder="Type department..."
                       value="{{ $analysis['department']->name ?? 'Whole Organisation' }}"
                       style="padding:5px 12px 5px 24px;font-size:12px;width:190px"
                       autocomplete="off">
                <div id="switchDeptDropdown"
                     style="display:none;position:absolute;top:calc(100% + 4px);right:0;width:280px;max-height:240px;overflow-y:auto;background:var(--hims-surface,#ffffff);border:1px solid var(--hims-border,#cbd5e1);border-radius:8px;box-shadow:0 10px 25px -4px rgba(0,0,0,0.12);z-index:9999">
                    <div class="switch-dept-item"
                         data-url="{{ route('competency.gap.department') }}"
                         data-name="Whole Organisation"
                         style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:12.5px;font-weight:{{ empty($departmentId) ? '600' : 'normal' }};background:{{ empty($departmentId) ? '#eff6ff' : '' }}">
                        <i class="bi bi-diagram-2" style="margin-right:6px"></i> Whole Organisation
                    </div>
                    @foreach($departments as $d)
                        <div class="switch-dept-item"
                             data-url="{{ route('competency.gap.department', ['department' => $d->department_id]) }}"
                             data-name="{{ $d->name }}"
                             style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:12.5px;display:flex;justify-content:space-between;align-items:center;background:{{ ($departmentId ?? null) === $d->department_id ? '#eff6ff' : '' }}">
                            <strong style="color:var(--hims-text,#0f172a)">{{ $d->name }}</strong>
                            <span class="hims-badge gray" style="font-size:10px">Dept</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="hims-card mb-4" style="border-left:4px solid var(--hims-primary)">
    <div class="card-header"><h5><i class="bi bi-robot"></i> AI Workforce Assessment</h5></div>
    <div class="card-body">
        @if(! $ai)
            <div style="color:#9ca3af;font-size:13px">
                No measured gaps to analyse — the AI layer is skipped when there is nothing below requirement.
            </div>
        @elseif(! empty($ai['unavailable']))
            <div class="hims-alert error" style="margin:0">
                <i class="bi bi-exclamation-circle-fill"></i> {{ $ai['message'] ?? 'AI analysis unavailable.' }}
                <div style="font-size:12px;margin-top:6px;color:#6b7280">
                    The measured table below is computed from the database and is unaffected.
                </div>
            </div>
        @else
            @if(!empty($ai['headline']))
            <p style="font-size:14.5px;font-weight:600;line-height:1.6;margin-bottom:16px">{{ $ai['headline'] }}</p>
            @endif

            @if(!empty($ai['patient_safety_note']))
            <div class="hims-alert" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;margin-bottom:16px">
                <i class="bi bi-shield-exclamation"></i> <strong>Patient safety:</strong> {{ $ai['patient_safety_note'] }}
            </div>
            @endif

            @if(!empty($ai['themes']))
            <div class="mb-3">
                <div class="hims-label" style="margin-bottom:8px">Themes</div>
                <div class="row g-2">
                    @foreach($ai['themes'] as $theme)
                    <div class="col-md-6">
                        <div style="padding:12px 14px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px;height:100%">
                            <strong style="font-size:13px">{{ $theme['theme'] ?? '—' }}</strong>
                            <div style="font-size:12px;color:#4b5563;margin-top:5px;line-height:1.6">{{ $theme['explanation'] ?? '' }}</div>
                            @if(!empty($theme['competencies']))
                            <div style="margin-top:7px;display:flex;gap:4px;flex-wrap:wrap">
                                @foreach((array) $theme['competencies'] as $competency)
                                <span class="hims-badge blue">{{ $competency }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($ai['recommended_actions']))
            <div class="hims-label" style="margin-bottom:8px">Recommended Actions</div>
            <table class="hims-table">
                <thead><tr><th>Action</th><th>Rationale</th><th>Timeframe</th><th>Owner</th></tr></thead>
                <tbody>
                    @foreach($ai['recommended_actions'] as $action)
                    <tr>
                        <td data-label="Action"><strong>{{ $action['action'] ?? '—' }}</strong></td>
                        <td data-label="Rationale" style="font-size:12px;color:#6b7280">{{ $action['rationale'] ?? '—' }}</td>
                        <td data-label="Timeframe" style="font-size:12px">{{ $action['timeframe'] ?? '—' }}</td>
                        <td data-label="Owner" style="font-size:12px">{{ $action['owner'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-graph-down-arrow"></i> Measured Weakest Competencies</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead>
                        <tr><th>Competency</th><th>Required</th><th>Avg</th><th>Avg Gap</th><th>Below</th><th>Assessed</th></tr>
                    </thead>
                    <tbody>
                        @forelse($analysis['weakest'] as $row)
                        <tr>
                            <td data-label="Competency">
                                <strong>{{ $row->competency_name }}</strong>
                                @if($row->is_mandatory)<span class="hims-badge red" style="margin-left:6px">Mandatory</span>@endif
                            </td>
                            <td data-label="Required">{{ $row->required_proficiency }}/5</td>
                            <td data-label="Avg">{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                            <td data-label="Avg Gap"><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                            <td data-label="Below">{{ $row->employees_below }}</td>
                            <td data-label="Assessed">{{ $row->assessed_employees }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                            No measured gaps in scope.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-mortarboard"></i> Training Demand</h5></div>
            <div class="card-body d-flex flex-column gap-3">
                @forelse($analysis['training_demand'] as $demand)
                <div style="padding:11px 13px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px">
                    <strong style="font-size:13px">{{ $demand['competency_name'] }}</strong>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">
                        {{ $demand['employees_below'] }} staff below requirement (avg gap {{ number_format($demand['avg_gap'], 2) }})
                    </div>
                    <div style="font-size:12px;color:var(--hims-primary);margin-top:5px;font-weight:600">
                        {{ $demand['suggested_format'] }}
                    </div>

                    {{-- What the catalogue already offers against this competency --}}
                    @if(!empty($demand['catalogue']))
                    <div style="margin-top:9px;padding-top:9px;border-top:1px dashed var(--hims-border)">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px">Available now</div>
                        @foreach($demand['catalogue'] as $item)
                        <div style="font-size:12px;margin-bottom:3px">
                            <i class="bi {{ $item['type'] === 'course' ? 'bi-journal-bookmark' : 'bi-calendar-event' }}" style="color:var(--hims-primary)"></i>
                            @if($item['type'] === 'course')
                                <a href="{{ route('learning.courses.show', $item['id']) }}" style="color:var(--hims-text-dark)">{{ $item['title'] }}</a>
                            @else
                                <a href="{{ route('training.sessions.show', $item['id']) }}" style="color:var(--hims-text-dark)">{{ $item['title'] }}</a>
                            @endif
                            <span style="color:#9ca3af">· {{ $item['detail'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div style="margin-top:9px;padding-top:9px;border-top:1px dashed var(--hims-border);font-size:11.5px;color:#b45309">
                        <i class="bi bi-exclamation-triangle"></i> Nothing in the catalogue is tagged to this competency yet.
                    </div>
                    @endif
                </div>
                @empty
                <div style="text-align:center;color:#9ca3af;padding:20px">No training demand identified.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const switchInput = document.getElementById('switchDeptInput');
    const switchDropdown = document.getElementById('switchDeptDropdown');
    if (switchInput && switchDropdown) {
        const items = switchDropdown.querySelectorAll('.switch-dept-item');

        function filterSwitchDepts(query) {
            query = (query || '').toLowerCase().trim();
            items.forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                const matches = !query || name.includes(query);
                item.style.display = matches ? (item.dataset.name.includes('Whole') ? 'block' : 'flex') : 'none';
            });
            switchDropdown.style.display = 'block';
        }

        switchInput.addEventListener('focus', function() {
            this.select();
            filterSwitchDepts('');
        });

        switchInput.addEventListener('input', function() {
            filterSwitchDepts(this.value);
        });

        items.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.background = '#f1f5f9';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = '';
            });
            item.addEventListener('click', function() {
                window.location.href = this.dataset.url;
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#switchDeptInput') && !e.target.closest('#switchDeptDropdown')) {
                switchDropdown.style.display = 'none';
            }
        });
    }
});
</script>
@endpush
