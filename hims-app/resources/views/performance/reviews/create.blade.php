@extends('layouts.hims')
@section('title','Start Performance Review')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Reviews / New')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Reviews</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-clipboard-check"></i> Start a Performance Review</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                @if(session('error'))
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
                @endif

                @if($cycles->isEmpty())
                    <div class="hims-alert mb-3" style="background:#fef3c7;border-color:#fde68a;color:#92400e">
                        <i class="bi bi-exclamation-triangle"></i>
                        There are no planned or active review cycles. A cycle has to exist before a review can be started.
                        @can('manage-review-cycles')
                        <div class="mt-2"><a href="{{ route('performance.cycles.create') }}" class="btn-hims btn-hims-primary btn-sm">Create a Cycle</a></div>
                        @endcan
                    </div>
                @else
                <form method="POST" action="{{ route('performance.reviews.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">Employee *</label>
                            <select name="employee_id" class="hims-input hims-select" required>
                                <option value="">— Select employee —</option>
                                @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}" @selected(old('employee_id') === $employee->employee_id)>
                                    {{ $employee->first_name }} {{ $employee->last_name }} — {{ $employee->position_title ?? 'No position' }} ({{ $employee->department_name }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Review Cycle *</label>
                            <select name="cycle_id" class="hims-input hims-select" required>
                                <option value="">— Select cycle —</option>
                                @foreach($cycles as $cycle)
                                <option value="{{ $cycle->cycle_id }}" @selected(old('cycle_id', $selectedCycle) === $cycle->cycle_id)>
                                    {{ $cycle->cycle_name }} ({{ ucfirst(str_replace('_',' ',$cycle->status)) }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Reviewer</label>
                            <select name="reviewer_id" class="hims-input hims-select">
                                <option value="">— Me ({{ Auth::user()->name }}) —</option>
                                @foreach($reviewers as $reviewer)
                                <option value="{{ $reviewer->employee_id }}" @selected(old('reviewer_id') === $reviewer->employee_id)>
                                    {{ $reviewer->first_name }} {{ $reviewer->last_name }}
                                </option>
                                @endforeach
                            </select>
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:4px">Leave blank to assign yourself as reviewer.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Review Type *</label>
                            <select name="review_type" class="hims-input hims-select" required>
                                <option value="standard" @selected(old('review_type','standard')==='standard')>Standard</option>
                                <option value="probationary" @selected(old('review_type')==='probationary')>Probationary</option>
                                <option value="promotion" @selected(old('review_type')==='promotion')>Promotion</option>
                                <option value="360" @selected(old('review_type')==='360')>360° Feedback</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="hims-label">KPIs to Assess</label>
                            <div style="font-size:11.5px;color:#9ca3af;margin-bottom:8px">
                                Tick the KPIs this review should score. You can add more later on the scoring screen.
                            </div>

                            @if($kpis->isEmpty())
                                <div style="color:#9ca3af;font-size:13px;padding:12px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px">
                                    No active KPIs in the library yet — the review will be created without any, and you can attach them later.
                                </div>
                            @else
                                @foreach($kpis->groupBy('kpi_category') as $category => $categoryKpis)
                                <div style="margin-bottom:14px">
                                    <div class="hims-label" style="margin-bottom:6px">{{ ucfirst(str_replace('_',' ',$category)) }}</div>
                                    <div class="row g-2">
                                        @foreach($categoryKpis as $kpi)
                                        <div class="col-md-6">
                                            <label style="display:flex;gap:9px;align-items:flex-start;padding:10px 12px;background:#f8fafc;border:1px solid var(--hims-border);border-radius:9px;cursor:pointer;height:100%">
                                                <input type="checkbox" name="kpi_ids[]" value="{{ $kpi->kpi_id }}" style="margin-top:3px"
                                                    @checked(in_array($kpi->kpi_id, (array) old('kpi_ids', $kpis->pluck('kpi_id')->all()), true))>
                                                <span>
                                                    <span style="font-size:13px;font-weight:600;display:block">{{ $kpi->kpi_name }}</span>
                                                    <span style="font-size:11.5px;color:#6b7280">
                                                        Weight {{ (float) $kpi->weight }}
                                                        @if($kpi->target_value) · target {{ $kpi->target_value }}{{ $kpi->unit ? ' '.$kpi->unit : '' }}@endif
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('performance.reviews.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create &amp; Score</button>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
