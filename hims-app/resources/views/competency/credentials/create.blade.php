@extends('layouts.hims')
@section('title','Add Credential')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / Credentials / Add')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('competency.credentials.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-patch-plus-fill"></i> Add Credential / License</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('competency.credentials.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Employee *</label>
                            <select name="employee_id" class="hims-input hims-select" required>
                                <option value="">— Select Employee —</option>
                                @foreach($employees ?? [] as $emp)
                                    <option value="{{ $emp->employee_id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Credential Type *</label>
                            <select name="credential_type" class="hims-input hims-select" required>
                                <option value="">— Select Type —</option>
                                <option value="PRC License">PRC License</option>
                                <option value="BLS Certification">BLS Certification</option>
                                <option value="ACLS Certification">ACLS Certification</option>
                                <option value="IV Therapy">IV Therapy</option>
                                <option value="Board Certificate">Board Certificate</option>
                                <option value="JCI Training">JCI Training</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">License/Certificate Number</label>
                            <input type="text" name="credential_number" class="hims-input" value="{{ old('credential_number') }}" placeholder="e.g. 0123456">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Issuing Body</label>
                            <input type="text" name="issuing_body" class="hims-input" value="{{ old('issuing_body') }}" placeholder="e.g. PRC, AHA">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="hims-input" value="{{ old('expiry_date') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Issue Date</label>
                            <input type="date" name="issue_date" class="hims-input" value="{{ old('issue_date') }}">
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('competency.credentials.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Add Credential</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
