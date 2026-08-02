@extends('layouts.hims')
@section('title','Critical Positions')
@section('page-title','Succession Planning')
@section('breadcrumb','HIMS / Succession / Critical Positions')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Critical Positions</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Roles the hospital cannot afford to leave vacant, ordered by risk.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('succession.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
        @can('manage-succession')
        <a href="{{ route('succession.positions.create') }}" class="btn-hims btn-hims-primary"><i class="bi bi-plus-lg"></i> Add Position</a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

<div class="hims-card">
    <div class="card-header"><h5><i class="bi bi-diagram-3"></i> Position Register</h5></div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Current Holder</th>
                    <th>Vacancy Risk</th>
                    <th>Est. Vacancy</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $position)
                @php
                    $risk = strtolower((string) $position->vacancy_risk);
                    $riskColour = match($risk) {
                        'critical' => 'red',
                        'high'     => 'red',
                        'medium'   => 'yellow',
                        'low'      => 'green',
                        default    => 'gray',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $position->position_title }}</strong>
                        @if($position->is_critical)
                            <span class="hims-badge red" style="margin-left:6px">Critical</span>
                        @endif
                        @if($position->impact_description)
                        <div style="font-size:11px;color:#6b7280;margin-top:3px;max-width:320px">{{ $position->impact_description }}</div>
                        @endif
                    </td>
                    <td>{{ $position->department_name }}</td>
                    <td>{{ trim((string) $position->current_holder_name) ?: '— vacant —' }}</td>
                    <td><span class="hims-badge {{ $riskColour }}">{{ ucfirst($risk ?: 'unknown') }}</span></td>
                    <td>
                        @if($position->estimated_vacancy_date)
                            {{ \Carbon\Carbon::parse($position->estimated_vacancy_date)->format('d M Y') }}
                        @else
                            <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('succession.positions.show', $position->position_id) }}" class="btn-hims btn-hims-outline btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:36px">
                    No critical positions registered yet.
                    @can('manage-succession')
                    <div class="mt-2"><a href="{{ route('succession.positions.create') }}" class="btn-hims btn-hims-primary btn-sm">Add the first one</a></div>
                    @endcan
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($positions->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $positions->links() }}</div>
    @endif
</div>
@endsection
