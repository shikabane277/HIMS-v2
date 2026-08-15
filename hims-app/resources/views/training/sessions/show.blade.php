@extends('layouts.hims')
@section('title','Session Detail')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / Session')
@section('content')
@php
    // Hoisted out of the Feedback Summary header, where it used to be computed
    // inline. The modal that replaced the feedback page is included at the foot
    // of this file, so the same answer has to be in scope in two places — and a
    // variable defined inside an @if branch is undefined when that branch does
    // not run.
    $sessionIsOver = $session->status === 'completed' || \Carbon\Carbon::parse($session->session_date)->isPast();
    $empId = auth()->user()->employee_id ?? null;
    $hasAttended = $sessionIsOver && $empId && DB::table('training_registrations')
        ->where('session_id', $session->session_id)
        ->where('employee_id', $empId)
        ->where('status', 'attended')
        ->exists();
    $hasFeedback = $empId && DB::table('training_feedback')
        ->where('session_id', $session->session_id)
        ->where('employee_id', $empId)
        ->exists();
    $canGiveFeedback = $hasAttended && ! $hasFeedback;
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('training.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
    <form method="POST" action="{{ route('training.register', $session->session_id) }}">
        @csrf
        <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-person-check"></i> Register Me</button>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="hims-card mb-3">
            <div style="padding:24px;background:linear-gradient(135deg,var(--hims-primary-pale),var(--hims-primary-xlight));border-bottom:1px solid var(--hims-border)">
                <div style="font-size:18px;font-weight:800;color:var(--hims-text-dark)">{{ $session->title }}</div>
                <div style="font-size:12px;color:#6b7280;margin-top:4px">{{ $session->session_code ?? '' }}</div>
                <div class="mt-2">
                    <span class="hims-badge {{ $session->status === 'scheduled' ? 'blue' : ($session->status === 'completed' ? 'green' : 'gray') }}">
                        {{ ucfirst($session->status) }}
                    </span>
                    <span class="hims-badge gray ms-1">{{ ucfirst($session->category) }}</span>
                </div>
            </div>
            <div class="card-body">
                <table style="width:100%;font-size:13px">
                    <tr style="border-bottom:1px solid var(--hims-border)"><td style="padding:9px 0;color:#6b7280;width:45%">Date</td><td style="padding:9px 0;font-weight:600">{{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}</td></tr>
                    <tr style="border-bottom:1px solid var(--hims-border)"><td style="padding:9px 0;color:#6b7280">Time</td><td style="padding:9px 0">{{ $session->start_time }} – {{ $session->end_time }}</td></tr>
                    <tr style="border-bottom:1px solid var(--hims-border)"><td style="padding:9px 0;color:#6b7280">Venue</td><td style="padding:9px 0">{{ $session->venue_name ?? 'Online' }}</td></tr>
                    <tr style="border-bottom:1px solid var(--hims-border)"><td style="padding:9px 0;color:#6b7280">Instructor</td><td style="padding:9px 0">{{ $session->instructor_name ?? '—' }}</td></tr>
                    <tr style="border-bottom:1px solid var(--hims-border)"><td style="padding:9px 0;color:#6b7280">CPD Hours</td><td style="padding:9px 0;font-weight:700;color:var(--hims-primary)">{{ $session->cpd_hours }} hrs</td></tr>
                    <tr><td style="padding:9px 0;color:#6b7280">Seats</td>
                        <td style="padding:9px 0">
                            <strong>{{ $session->registered_count ?? 0 }}</strong>/{{ $session->capacity }}
                            <div class="hims-progress mt-1"><div class="hims-progress-bar" style="width:{{ $session->capacity > 0 ? min(100,round(($session->registered_count??0)/$session->capacity*100)) : 0 }}%"></div></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @if($session->description)
        <div class="hims-card mb-3">
            <div class="card-header"><h5><i class="bi bi-info-circle"></i> About this Session</h5></div>
            <div class="card-body"><p style="font-size:13.5px;line-height:1.8;margin:0">{{ $session->description }}</p></div>
        </div>
        @endif

        <div class="hims-card mb-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-people-fill"></i> Registrations ({{ $session->registered_count ?? 0 }})</h5>
                    @can('manage-training')
                        @if($session->status === 'scheduled' && count($registrations ?? []) > 0)
                            <button type="button" class="btn-hims btn-hims-outline" style="font-size:13px;padding:6px 14px" onclick="document.getElementById('checkin-form').style.display = document.getElementById('checkin-form').style.display === 'none' ? '' : 'none'">
                                <i class="bi bi-check2-square"></i> Mark Attendance
                            </button>
                        @endif
                    @endcan
                </div>
            </div>
            <div class="card-body" style="padding:0">
                @can('manage-training')
                    <form method="POST" action="{{ route('training.sessions.checkin', $session->session_id) }}" id="checkin-form" style="display:none;padding:20px;background:var(--hims-bg);border-bottom:1px solid var(--hims-border)">
                        @csrf
                        <div style="margin-bottom:12px;font-weight:600;font-size:13px">Mark each registrant as attended or no-show:</div>
                        @foreach($registrations ?? [] as $reg)
                            <div style="padding:8px 0;border-bottom:1px solid var(--hims-border-light)">
                                <div style="font-weight:600;margin-bottom:4px">{{ $reg->employee_name }}</div>
                                <label style="margin-right:16px;cursor:pointer">
                                    <input type="radio" name="registrations[{{ $reg->registration_id }}]" value="attended" @checked($reg->status === 'attended')> Attended
                                </label>
                                <label style="cursor:pointer">
                                    <input type="radio" name="registrations[{{ $reg->registration_id }}]" value="no_show" @checked($reg->status === 'no_show')> No-show
                                </label>
                            </div>
                        @endforeach
                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <button type="button" class="btn-hims btn-hims-outline" onclick="document.getElementById('checkin-form').style.display = 'none'">Cancel</button>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Attendance</button>
                        </div>
                    </form>
                @endcan
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Registered</th></tr></thead>
                    <tbody>
                        @forelse($registrations ?? [] as $reg)
                        <tr>
                            <td><strong>{{ $reg->employee_name }}</strong></td>
                            <td>{{ $reg->department_name ?? '—' }}</td>
                            <td><span class="hims-badge {{ $reg->status === 'attended' ? 'green' : ($reg->status === 'no_show' ? 'red' : 'yellow') }}">{{ ucfirst(str_replace('_',' ',$reg->status)) }}</span></td>
                            <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($reg->registration_date)->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:24px">No registrations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hims-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-chat-square-text"></i> Feedback Summary</h5>
                    @if($canGiveFeedback)
                        <button type="button" class="btn-hims btn-hims-primary" style="font-size:13px;padding:6px 14px" data-modal-open="feedbackModal">
                            <i class="bi bi-pencil"></i> Give Feedback
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(($session->avg_rating ?? 0) > 0)
                    <div style="text-align:center;margin-bottom:16px">
                        <div style="font-size:36px;font-weight:800;color:var(--hims-primary)">{{ number_format($session->avg_rating,1) }}</div>
                        <div style="font-size:12px;color:#6b7280">Average Rating</div>
                    </div>
                @else
                    <div style="text-align:center;color:#9ca3af;padding:24px">No feedback submitted yet.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canGiveFeedback)
    @include('training.sessions._feedback-modal')
@endif
@endsection
