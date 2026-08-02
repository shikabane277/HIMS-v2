@extends('layouts.hims')
@section('title','Learning Pathways')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Pathways')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Learning Pathways</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Structured course sequences for role-based development.</p>
    </div>
    <a href="{{ route('learning.pathways.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-plus-circle"></i> New Pathway</a>
</div>
<div class="row g-3">
    @forelse($pathways ?? [] as $pathway)
    <div class="col-md-6 col-lg-4">
        <div class="hims-card" style="transition:.2s" onmouseover="this.style.boxShadow='var(--hims-shadow-md)'" onmouseout="this.style.boxShadow='var(--hims-shadow)'">
            <div class="card-header">
                <h5 style="font-size:14px"><i class="bi bi-signpost-split"></i> {{ $pathway->pathway_name }}</h5>
                @if($pathway->is_mandatory)<span class="hims-badge red">Mandatory</span>@endif
            </div>
            <div class="card-body">
                <div style="display:flex;gap:20px;margin-bottom:12px">
                    <div style="text-align:center">
                        <div style="font-size:22px;font-weight:800;color:var(--hims-primary)">{{ $pathway->courses_count ?? 0 }}</div>
                        <div style="font-size:11px;color:#9ca3af">Courses</div>
                    </div>
                    <div style="text-align:center">
                        <div style="font-size:22px;font-weight:800;color:var(--hims-text-dark)">{{ $pathway->total_cpd_hours ?? 0 }}</div>
                        <div style="font-size:11px;color:#9ca3af">CPD Hrs</div>
                    </div>
                </div>
                @if($pathway->description)
                <p style="font-size:12.5px;color:#6b7280;margin-bottom:12px;line-height:1.6">{{ Str::limit($pathway->description,100) }}</p>
                @endif
                @php
                    $targets = $pathway->target_roles ? json_decode($pathway->target_roles, true) : [];
                    $targetCount = is_array($targets) ? count($targets) : 0;
                @endphp
                <div class="mt-2">
                    <span class="hims-badge blue">{{ $targetCount ? $targetCount.' target role'.($targetCount === 1 ? '' : 's') : 'All roles' }}</span>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="hims-card">
            <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
                <div style="font-size:48px;margin-bottom:12px">🗺️</div>
                <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No pathways yet</div>
                <a href="{{ route('learning.pathways.create') }}" class="btn-hims btn-hims-primary mt-2">Create First Pathway</a>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
