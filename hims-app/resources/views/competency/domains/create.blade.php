@extends('layouts.hims')
@section('title','New Competency Domain')
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / New Domain')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-diagram-2"></i> New Competency Domain</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <p style="font-size:13px;color:#6b7280;margin-bottom:18px">
                    A domain is the top level of the competency framework — for example
                    <em>Clinical Care</em> or <em>Patient Safety &amp; Quality</em>. Categories and individual
                    competencies sit underneath it.
                </p>

                <form method="POST" action="{{ route('competency.domains.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Domain Name *</label>
                            <input type="text" name="domain_name" class="hims-input" required maxlength="100"
                                   value="{{ old('domain_name') }}" placeholder="e.g. Clinical Care">
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:4px">Must be unique across the framework.</div>
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Description</label>
                            <textarea name="description" class="hims-input" rows="4"
                                      placeholder="What this domain covers and who it applies to…">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Domain</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
