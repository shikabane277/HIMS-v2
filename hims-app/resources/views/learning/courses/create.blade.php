@extends('layouts.hims')
@section('title','Create Course')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / New Course')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('learning.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-plus-circle"></i> Create New Course</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('learning.courses.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Course Title *</label>
                            <input type="text" name="title" class="hims-input" value="{{ old('title') }}" required placeholder="e.g. Basic Life Support (BLS) Certification">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Course Code</label>
                            <input type="text" name="course_code" class="hims-input" value="{{ old('course_code') }}" placeholder="e.g. CLN-001">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Category *</label>
                            <select name="category" class="hims-input hims-select" required>
                                <option value="">— Select —</option>
                                <option value="clinical">Clinical</option>
                                <option value="compliance">Compliance</option>
                                <option value="soft_skills">Soft Skills</option>
                                <option value="leadership">Leadership</option>
                                <option value="technical">Technical</option>
                                <option value="safety">Safety</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">CPD Hours *</label>
                            <input type="number" name="cpd_hours" class="hims-input" value="{{ old('cpd_hours',1) }}" required min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">Difficulty Level *</label>
                            <select name="difficulty_level" class="hims-input hims-select" required>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate" selected>Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">Passing Score (%)</label>
                            <input type="number" name="passing_score" class="hims-input" value="{{ old('passing_score',70) }}" min="0" max="100">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Duration (minutes)</label>
                            <input type="number" name="estimated_duration" class="hims-input" value="{{ old('estimated_duration') }}" min="1" placeholder="e.g. 120">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Mandatory?</label>
                            <select name="is_mandatory" class="hims-input hims-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Description</label>
                            <textarea name="description" class="hims-input" rows="4" placeholder="Course objectives and overview…">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('learning.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Course</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
