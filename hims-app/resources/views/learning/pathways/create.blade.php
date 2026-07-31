@extends('layouts.hims')
@section('title','Create Pathway')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / New Pathway')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('learning.pathways.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-diagram-2"></i> New Learning Pathway</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('learning.pathways.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Pathway Name *</label>
                            <input type="text" name="pathway_name" class="hims-input" value="{{ old('pathway_name') }}" required placeholder="e.g. New Nurse Orientation Pathway">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Mandatory?</label>
                            <select name="is_mandatory" class="hims-input hims-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Target Role / Department</label>
                            <input type="text" name="target_role" class="hims-input" value="{{ old('target_role') }}" placeholder="e.g. Staff Nurse">
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Description</label>
                            <textarea name="description" class="hims-input" rows="3" placeholder="Learning goals and target audience…">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('learning.pathways.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Pathway</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
