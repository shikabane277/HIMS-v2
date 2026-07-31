@extends('layouts.hims')
@section('title','Schedule Training Session')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / New Session')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('training.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-calendar-plus"></i> Schedule New Training Session</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('training.sessions.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Session Title *</label>
                            <input type="text" name="title" class="hims-input" value="{{ old('title') }}" required placeholder="e.g. Basic Life Support Refresher">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Category *</label>
                            <select name="category" class="hims-input hims-select" required>
                                <option value="">— Select —</option>
                                <option value="clinical">Clinical</option>
                                <option value="fire_safety">Fire Safety</option>
                                <option value="compliance">Compliance</option>
                                <option value="leadership">Leadership</option>
                                <option value="soft_skills">Soft Skills</option>
                                <option value="technical">Technical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Instructor *</label>
                            <select name="instructor_id" class="hims-input hims-select" required>
                                <option value="">— Select Instructor —</option>
                                @foreach($instructors as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Venue</label>
                            <select name="venue_id" class="hims-input hims-select">
                                <option value="">— Online / TBD —</option>
                                @foreach($venues as $v)
                                    <option value="{{ $v->venue_id }}">{{ $v->venue_name }} (cap: {{ $v->capacity }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Max Capacity *</label>
                            <input type="number" name="capacity" class="hims-input" value="{{ old('capacity',30) }}" required min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">Session Date *</label>
                            <input type="date" name="session_date" class="hims-input" value="{{ old('session_date') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">Start Time *</label>
                            <input type="time" name="start_time" class="hims-input" value="{{ old('start_time') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">End Time *</label>
                            <input type="time" name="end_time" class="hims-input" value="{{ old('end_time') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">CPD Hours</label>
                            <input type="number" name="cpd_hours" class="hims-input" value="{{ old('cpd_hours',0) }}" step="0.5" min="0">
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Description</label>
                            <textarea name="description" class="hims-input" rows="3" placeholder="Session objectives and agenda…">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('training.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Schedule Session</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
