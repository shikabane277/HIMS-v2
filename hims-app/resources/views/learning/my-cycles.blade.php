@extends('layouts.hims')
@section('title','My Renewal Cycles')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / My Renewal Cycles')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">
            @if($employee)
                {{ $employee->first_name }} {{ $employee->last_name }} — Renewal Cycles
            @else
                My Renewal Cycles
            @endif
        </h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            What is owed in the <em>current</em> window, not a lifetime total. Only CPD that has been
            verified counts — logging hours does not clear a requirement on its own.
        </p>
    </div>
    {{-- Logging CPD is a modal on the CPD log page. A button here cannot open a
         modal there, so this is a deep-link that opens it on arrival. --}}
    <a href="{{ route('learning.cpd.index', ['new' => 'cpd']) }}" class="btn-hims btn-hims-primary">
        <i class="bi bi-plus-circle"></i> Log CPD
    </a>
</div>

@if($unlinked)
<div class="hims-alert warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Your login is not linked to an employee record, so there is nothing to track hours against.
    An administrator can link it from <strong>User Management</strong>.
</div>
@else

@php
    $open = $cycles->where('status', 'open');
    $owed = round($open->sum('hours_remaining'), 1);
@endphp

<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">🔄</div><div class="stat-value">{{ $open->count() }}</div><div class="stat-label">Open Cycles</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">⏱️</div><div class="stat-value">{{ rtrim(rtrim(number_format($owed, 1), '0'), '.') }}</div><div class="stat-label">Hours Still Owed</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="stat-icon">⚠️</div><div class="stat-value">{{ $cycles->whereIn('risk', ['at_risk','shortfall'])->count() }}</div><div class="stat-label">Behind Pace</div></div></div>
</div>

@forelse($cycles as $cycle)
<div class="hims-card mb-3">
    <div class="card-header">
        <h5>
            <i class="bi bi-{{ $cycle->subject_type === 'credential' ? 'patch-check' : 'mortarboard' }}"></i>
            {{ $cycle->label }}
        </h5>
        @if($cycle->risk === 'met')
            <span class="hims-badge green">✓ Requirement met</span>
        @elseif($cycle->risk === 'shortfall')
            <span class="hims-badge red">Closed short</span>
        @elseif($cycle->risk === 'at_risk')
            <span class="hims-badge yellow">Behind pace</span>
        @else
            <span class="hims-badge blue">On track</span>
        @endif
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between" style="font-size:12.5px;margin-bottom:6px">
            <span>
                <strong>{{ $cycle->hours_attained }}</strong> of
                {{ rtrim(rtrim(number_format($cycle->hours_required_snapshot, 1), '0'), '.') }} verified hours
            </span>
            <strong>{{ $cycle->pct_complete }}%</strong>
        </div>
        <div class="hims-progress mb-3"><div class="hims-progress-bar" style="width:{{ $cycle->pct_complete }}%"></div></div>

        <div class="row g-3" style="font-size:12.5px">
            <div class="col-sm-3">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Window</div>
                {{ \Carbon\Carbon::parse($cycle->cycle_start)->format('M d, Y') }} —
                {{ \Carbon\Carbon::parse($cycle->cycle_end)->format('M d, Y') }}
            </div>
            <div class="col-sm-3">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Time Left</div>
                @if($cycle->days_left >= 0)
                    {{ $cycle->days_left }} day(s)
                @else
                    Closed {{ abs($cycle->days_left) }} day(s) ago
                @endif
                @if($cycle->grace_days)
                    <div style="color:#9ca3af;font-size:11px">+{{ $cycle->grace_days }} day grace</div>
                @endif
            </div>
            <div class="col-sm-3">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">Still Needed</div>
                @if($cycle->hours_remaining > 0)
                    <strong>{{ $cycle->hours_remaining }}</strong> hrs
                @else
                    <span style="color:#16a34a">Nothing outstanding</span>
                @endif
            </div>
            <div class="col-sm-3">
                <div style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.03em">
                    {{ $cycle->subject_type === 'credential' ? 'Credential' : 'CPD Category' }}
                </div>
                {{ $cycle->subject_key }}
                @if($cycle->credential_number)
                    <div style="color:#9ca3af;font-size:11px">{{ $cycle->credential_number }}</div>
                @endif
                @if($cycle->credential_status)
                    <span class="hims-badge {{ \App\Support\CredentialStatus::badgeClass($cycle->credential_status) }}" style="margin-top:4px">
                        {{ \App\Support\CredentialStatus::label($cycle->credential_status) }}
                    </span>
                @endif
            </div>
        </div>

        @if($cycle->risk === 'at_risk' && $cycle->days_left > 0)
        <div class="hims-alert warning mt-3" style="margin-bottom:0">
            <i class="bi bi-exclamation-triangle-fill"></i>
            At the current pace this window closes short. {{ $cycle->hours_remaining }} hour(s) in
            {{ $cycle->days_left }} day(s) —
            <a href="{{ route('learning.cpd.index', ['new' => 'cpd']) }}" class="text-primary-hims">log CPD you have already earned</a>,
            or ask your supervisor to put you on a course that carries the hours.
        </div>
        @endif
    </div>
</div>
@empty
<div class="hims-card">
    <div class="card-body text-center" style="color:#9ca3af;padding:40px">
        No renewal cycle applies to you. Credential rules only attach to people who hold that
        credential, so this stays empty until one is recorded against your profile.
    </div>
</div>
@endforelse

@endif
@endsection
