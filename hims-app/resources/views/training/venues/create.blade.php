@extends('layouts.hims')
@section('title','Add Venue')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / Venues / Add')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('training.venues.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-building-add"></i> Add Training Venue</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('training.venues.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Venue Name *</label>
                            <input type="text" name="venue_name" class="hims-input" value="{{ old('venue_name') }}" required placeholder="e.g. Training Center A">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Building</label>
                            <input type="text" name="building" class="hims-input" value="{{ old('building') }}" placeholder="e.g. Main Building">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Floor</label>
                            <input type="text" name="floor" class="hims-input" value="{{ old('floor') }}" placeholder="e.g. 3rd Floor">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Capacity *</label>
                            <input type="number" name="capacity" class="hims-input" value="{{ old('capacity') }}" required min="1" placeholder="50">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Equipment</label>
                            <input type="text" name="equipment" class="hims-input" value="{{ old('equipment') }}" placeholder="e.g. Projector, Whiteboard">
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('training.venues.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Add Venue</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
