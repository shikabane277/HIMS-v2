@extends('layouts.hims')
@section('title','New Competency Assessment')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / New Assessment')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-clipboard-check"></i> New Competency Assessment</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('competency.assessments.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">Employee *</label>
                            <select name="employee_id" class="hims-input hims-select" required>
                                <option value="">— Select Employee —</option>
                                @foreach($employees ?? [] as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Competency *</label>
                            <select name="competency_id" class="hims-input hims-select" required>
                                <option value="">— Select Competency —</option>
                                @foreach($competencies ?? [] as $comp)
                                    <option value="{{ $comp->competency_id }}">{{ $comp->competency_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Current Proficiency (1–5) *</label>
                            <input type="number" name="current_proficiency" class="hims-input" value="{{ old('current_proficiency') }}" required min="1" max="5" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Assessment Method</label>
                            <select name="assessment_method" class="hims-input hims-select">
                                <option value="self_assessment">Self Assessment</option>
                                <option value="supervisor_rating">Supervisor Rating</option>
                                <option value="practical_test">Practical Test</option>
                                <option value="written_exam">Written Exam</option>
                                <option value="observation">Direct Observation</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Assessment Date</label>
                            <input type="date" name="assessed_date" class="hims-input" value="{{ old('assessed_date', now()->toDateString()) }}">
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Notes</label>
                            <textarea name="notes" class="hims-input" rows="3" placeholder="Observations and recommendations…">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Assessment</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
