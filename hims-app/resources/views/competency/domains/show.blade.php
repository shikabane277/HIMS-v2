@extends('layouts.hims')
@section('title',$domain->domain_name)
@section('page-title','Competency Management')
@section('breadcrumb','HIMS / Competency / Domain')
@section('content')

@php
    $byCategory = $competencies->groupBy('category_id');
    $mandatoryCount = $competencies->where('is_mandatory', true)->count();
@endphp

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $domain->domain_name }}</h2>
        @if($domain->description)
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0;max-width:640px">{{ $domain->description }}</p>
        @endif
    </div>
    <a href="{{ route('competency.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back to Competency</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="bi bi-folder"></i></div>
            <div class="stat-value">{{ $categories->count() }}</div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-bullseye"></i></div>
            <div class="stat-value">{{ $competencies->count() }}</div>
            <div class="stat-label">Competencies</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-shield-check"></i></div>
            <div class="stat-value">{{ $mandatoryCount }}</div>
            <div class="stat-label">Hospital-Wide Mandatory</div>
        </div>
    </div>
</div>

@forelse($categories as $category)
<div class="hims-card mb-3">
    <div class="card-header">
        <h5><i class="bi bi-folder2-open"></i> {{ $category->category_name }}</h5>
        <div class="d-flex gap-2 align-items-center">
            @if($category->jci_standard_code)
                <span class="hims-badge blue">JCI {{ $category->jci_standard_code }}</span>
            @endif
            <span style="font-size:11.5px;color:#9ca3af">
                {{ $category->competency_count }} competenc{{ (int) $category->competency_count === 1 ? 'y' : 'ies' }}
            </span>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Competency</th>
                    <th style="width:110px">Code</th>
                    <th style="width:130px">Required Level</th>
                    <th style="width:110px">Scope</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byCategory->get($category->category_id, collect()) as $competency)
                <tr>
                    <td>
                        <strong>{{ $competency->competency_name }}</strong>
                        @if($competency->description)
                        <div style="font-size:11.5px;color:#6b7280;margin-top:3px;max-width:520px">{{ $competency->description }}</div>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-size:12px">{{ $competency->competency_code ?? '—' }}</td>
                    <td>
                        <span class="hims-badge {{ $competency->required_proficiency >= 4 ? 'red' : ($competency->required_proficiency === 3 ? 'yellow' : 'green') }}">
                            Level {{ $competency->required_proficiency }}/5
                        </span>
                    </td>
                    <td>
                        @if($competency->is_mandatory)
                            <span class="hims-badge red">Mandatory</span>
                        @else
                            <span class="hims-badge gray">Role-based</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:24px">
                    No competencies defined in this category yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="hims-card">
    <div class="card-body" style="text-align:center;padding:56px;color:#9ca3af">
        <div style="font-size:44px;margin-bottom:10px">📂</div>
        <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No categories yet</div>
        <p style="font-size:13px;max-width:420px;margin:0 auto">
            This domain has no categories, so there are no competencies to measure against.
            Seed the competency framework or add categories to start using it.
        </p>
    </div>
</div>
@endforelse
@endsection
