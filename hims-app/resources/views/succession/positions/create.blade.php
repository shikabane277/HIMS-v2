@extends('layouts.hims')
@section('title','Add Critical Position')
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Positions / Add')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-briefcase-fill"></i> Add Critical Position</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('succession.positions.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Position Title *</label>
                            <input type="text" name="position_title" class="hims-input" value="{{ old('position_title') }}" required placeholder="e.g. Chief Nursing Officer">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Department *</label>
                            <select name="department_id" class="hims-input hims-select" required>
                                <option value="">— Select Department —</option>
                                @foreach($departments ?? [] as $dept)
                                    <option value="{{ $dept->department_id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Current Holder</label>
                            <select name="current_holder_id" class="hims-input hims-select">
                                <option value="">— Vacant —</option>
                                @foreach($employees ?? [] as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Vacancy Risk</label>
                            <select name="vacancy_risk" class="hims-input hims-select">
                                <option value="low">🟢 Low</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="high">🟠 High</option>
                                <option value="critical">🔴 Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Estimated Vacancy Date</label>
                            <input type="date" name="estimated_vacancy_date" class="hims-input" value="{{ old('estimated_vacancy_date') }}">
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Add Position</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
