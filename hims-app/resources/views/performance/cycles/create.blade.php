@extends('layouts.hims')
@section('title','Create Review Cycle')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / New Cycle')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-calendar-plus"></i> New Review Cycle</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('performance.cycles.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Cycle Name *</label>
                            <input type="text" name="cycle_name" class="hims-input" value="{{ old('cycle_name') }}" required placeholder="e.g. 2026 Annual Performance Review">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Cycle Type *</label>
                            <select name="cycle_type" class="hims-input hims-select" required>
                                <option value="">— Select —</option>
                                <option value="annual" {{ old('cycle_type')=='annual'?'selected':'' }}>Annual</option>
                                <option value="semi_annual" {{ old('cycle_type')=='semi_annual'?'selected':'' }}>Semi-Annual</option>
                                <option value="quarterly" {{ old('cycle_type')=='quarterly'?'selected':'' }}>Quarterly</option>
                                <option value="probationary" {{ old('cycle_type')=='probationary'?'selected':'' }}>Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <label class="hims-label">Start Date *</label>
                            <input type="date" name="start_date" class="hims-input" value="{{ old('start_date') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">End Date *</label>
                            <input type="date" name="end_date" class="hims-input" value="{{ old('end_date') }}" required>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Cycle</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
