@extends('layouts.hims')
@section('title','Edit Review Cycle')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Cycles / Edit')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Edit Review Cycle</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">{{ $cycle->cycle_name }}</p>
    </div>
    <a href="{{ route('performance.cycles.show', $cycle->cycle_id) }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-pencil-square"></i> Cycle Details</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('performance.cycles.update', $cycle->cycle_id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Cycle Name *</label>
                            <input type="text" name="cycle_name" class="hims-input" required
                                   value="{{ old('cycle_name', $cycle->cycle_name) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Cycle Type *</label>
                            @php $type = old('cycle_type', $cycle->cycle_type); @endphp
                            <select name="cycle_type" class="hims-input hims-select" required>
                                <option value="annual"       @selected($type==='annual')>Annual</option>
                                <option value="semi_annual"  @selected($type==='semi_annual')>Semi-Annual</option>
                                <option value="quarterly"    @selected($type==='quarterly')>Quarterly</option>
                                <option value="probationary" @selected($type==='probationary')>Probationary</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Status *</label>
                            @php $status = old('status', $cycle->status); @endphp
                            <select name="status" class="hims-input hims-select" required>
                                <option value="planned"  @selected($status==='planned')>Planned</option>
                                <option value="active"   @selected($status==='active')>Active</option>
                                <option value="closed"   @selected($status==='closed')>Closed</option>
                                <option value="archived" @selected($status==='archived')>Archived</option>
                            </select>
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:5px">
                                Only planned and active cycles can take new reviews.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Start Date *</label>
                            <input type="date" name="start_date" class="hims-input" required
                                   value="{{ old('start_date', \Illuminate\Support\Str::substr((string) $cycle->start_date, 0, 10)) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">End Date *</label>
                            <input type="date" name="end_date" class="hims-input" required
                                   value="{{ old('end_date', \Illuminate\Support\Str::substr((string) $cycle->end_date, 0, 10)) }}">
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('performance.cycles.show', $cycle->cycle_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
