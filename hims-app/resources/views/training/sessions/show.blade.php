@extends('layouts.hims')
@section('title','Session Detail')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / Session')
@section('content')
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
            <div class="card-header"><h5><i class="bi bi-people-fill"></i> Registrations ({{ $session->registered_count ?? 0 }})</h5></div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Registered</th></tr></thead>
                    <tbody>
                        @forelse($registrations ?? [] as $reg)
                        <tr>
                            <td><strong>{{ $reg->employee_name }}</strong></td>
                            <td>{{ $reg->department_name ?? '—' }}</td>
                            <td><span class="hims-badge {{ $reg->status === 'attended' ? 'green' : ($reg->status === 'absent' ? 'red' : 'yellow') }}">{{ ucfirst($reg->status) }}</span></td>
                            <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($reg->registration_date)->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">No registrations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-chat-square-text"></i> Feedback Summary</h5></div>
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
@endsection
