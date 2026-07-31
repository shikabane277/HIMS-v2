@extends('layouts.hims')
@section('title','Create Badge')
@section('page-title','Social Recognition')
@section('breadcrumb','HIMS / Recognition / New Badge')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-patch-plus-fill"></i> Create New Badge</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('recognition.badges.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="hims-label">Badge Name *</label>
                            <input type="text" name="badge_name" class="hims-input" value="{{ old('badge_name') }}" required placeholder="e.g. Patient Champion">
                        </div>
                        <div class="col-md-4">
                            <label class="hims-label">Icon (emoji)</label>
                            <input type="text" name="badge_icon" class="hims-input" value="{{ old('badge_icon','🏅') }}" placeholder="🏅" maxlength="4">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Hospital Value</label>
                            <input type="text" name="hospital_value" class="hims-input" value="{{ old('hospital_value') }}" placeholder="e.g. Compassion, Excellence">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Points Value *</label>
                            <input type="number" name="points_value" class="hims-input" value="{{ old('points_value',5) }}" required min="1">
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Description</label>
                            <textarea name="description" class="hims-input" rows="3" placeholder="What behaviour does this badge recognise?">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Badge</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
