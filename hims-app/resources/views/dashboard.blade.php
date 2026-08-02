@extends('layouts.hims')
@section('title','Dashboard')
@section('page-title', $heading ?? 'Dashboard')
@section('breadcrumb','HIMS / Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $heading ?? 'Dashboard' }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">{{ $subheading ?? '' }}</p>
    </div>
    <span class="hims-badge blue"><i class="bi bi-eye"></i> Scope: {{ $scope ?? 'Whole organisation' }}</span>
</div>

@if(($role ?? 'staff') === 'staff')
    @include('dashboard.partials.staff')
@elseif(($role ?? '') === 'supervisor')
    @include('dashboard.partials.supervisor')
@else
    @include('dashboard.partials.organisation')
@endif
@endsection
