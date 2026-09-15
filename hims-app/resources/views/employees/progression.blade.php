@extends('layouts.hims')
@section('title','Development Progression')
@section('page-title','Employee Development')
@section('breadcrumb','HIMS / Employees / Progression')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">
            {{ $employee->first_name }} {{ $employee->last_name }}
        </h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $employee->position_title }} · {{ $employee->department_name }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost">
            <i class="bi bi-arrow-left"></i> Back to Directory
        </a>
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3" data-auto-dismiss><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
@endif

{{-- Overview Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value">{{ $gaps->count() }}</div>
            <div class="stat-label">Open Gaps</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-shield-check"></i></div>
            <div class="stat-value">{{ $credentials->count() }}</div>
            <div class="stat-label">Credentials</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value">{{ number_format($cpdTotal, 1) }}</div>
            <div class="stat-label">CPD Hours (12mo)</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card animate-in">
            <div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-value">{{ $upcomingReassessments->count() }}</div>
            <div class="stat-label">Due Soon</div>
        </div>
    </div>
</div>

{{-- Open competency gaps. gap is the trigger-computed column; the controller
     already reduced this to the latest sitting per competency. --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-exclamation-triangle"></i> Open Competency Gaps</h5>
            </div>
            <div class="card-body" style="padding:0">
                @if($gaps->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Competency</th>
                            <th>Category</th>
                            <th class="text-center" style="width:90px">Required</th>
                            <th class="text-center" style="width:90px">Current</th>
                            <th class="text-center" style="width:90px">Gap</th>
                            <th style="width:130px">Last Assessed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gaps as $g)
                        <tr>
                            <td data-label="Competency">
                                <strong>{{ $g->competency_name }}</strong>
                                @if($g->is_mandatory)<span class="hims-badge red" style="margin-left:6px;font-size:10px">Mandatory</span>@endif
                                @if($g->competency_code)<div style="font-size:11px;color:#9ca3af;font-family:monospace">{{ $g->competency_code }}</div>@endif
                            </td>
                            <td data-label="Category">{{ $g->category_name ?: '—' }}</td>
                            <td data-label="Required" class="text-center">{{ $g->required_proficiency }}</td>
                            <td data-label="Current" class="text-center">{{ $g->current_proficiency }}</td>
                            <td data-label="Gap" class="text-center"><span class="gap-chip negative">{{ $g->gap }}</span></td>
                            <td data-label="Last Assessed">{{ $g->assessed_date ? \Carbon\Carbon::parse($g->assessed_date)->format('d M Y') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">
                    <i class="bi bi-check-circle" style="font-size:40px;display:block;margin-bottom:10px;color:#16a34a"></i>
                    No open competency gaps on record.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Credentials. status comes from CredentialStatus::caseSql(), the same
         expression the nightly expiry scan and the dashboard read, so this page
         cannot disagree with the alert someone was sent. --}}
    <div class="col-lg-6">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-shield-check"></i> Credentials</h5></div>
            <div class="card-body" style="padding:0">
                @if($credentials->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Credential</th>
                            <th style="width:120px">Expires</th>
                            <th style="width:120px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($credentials as $cr)
                        @php $days = \App\Support\CredentialStatus::daysRemaining($cr->expiry_date); @endphp
                        <tr>
                            <td data-label="Credential">
                                <strong>{{ $cr->credential_type }}</strong>
                                @if($cr->issuing_body)<div style="font-size:11px;color:#9ca3af">{{ $cr->issuing_body }}</div>@endif
                            </td>
                            <td data-label="Expires">
                                @if($cr->expiry_date)
                                    {{ \Carbon\Carbon::parse($cr->expiry_date)->format('d M Y') }}
                                    <div style="font-size:11px;color:#9ca3af">
                                        {{ $days < 0 ? abs($days).' days ago' : 'in '.$days.' days' }}
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="hims-badge {{ \App\Support\CredentialStatus::badgeClass($cr->status) }}">
                                    {{ \App\Support\CredentialStatus::label($cr->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">No credentials on file.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Reassessments falling due. Due date is the per-assessment override where
         one was set, otherwise the competency's own cadence interval. --}}
    <div class="col-lg-6">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-calendar-event"></i> Reassessments Due</h5></div>
            <div class="card-body" style="padding:0">
                @if($upcomingReassessments->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Competency</th>
                            <th style="width:120px">Last Assessed</th>
                            <th style="width:130px">Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcomingReassessments as $ra)
                        @php $overdue = $ra->due_date < now()->toDateString(); @endphp
                        <tr>
                            <td data-label="Competency">
                                <strong>{{ $ra->competency_name }}</strong>
                                @if($ra->category_name)<div style="font-size:11px;color:#9ca3af">{{ $ra->category_name }}</div>@endif
                            </td>
                            <td data-label="Last Assessed">{{ \Carbon\Carbon::parse($ra->last_assessed)->format('d M Y') }}</td>
                            <td data-label="Due">
                                <span class="hims-badge {{ $overdue ? 'red' : 'yellow' }}">
                                    {{ \Carbon\Carbon::parse($ra->due_date)->format('d M Y') }}
                                </span>
                                @if($ra->override_due)
                                    <div style="font-size:10.5px;color:#9ca3af">set by assessor</div>
                                @elseif($ra->reassessment_months)
                                    <div style="font-size:10.5px;color:#9ca3af">every {{ $ra->reassessment_months }} mo</div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">
                    Nothing falls due in the next 90 days.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Course enrollments and training registrations. --}}
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-journal-plus"></i> Course Activity</h5></div>
            <div class="card-body" style="padding:0">
                @if($enrollments->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th style="width:110px">Status</th>
                            <th class="text-center" style="width:90px">Progress</th>
                            <th class="text-center" style="width:70px">CPD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $en)
                        <tr>
                            <td data-label="Course">
                                <strong>{{ $en->title }}</strong>
                                <div style="font-size:11px;color:#9ca3af">{{ ucfirst(str_replace('_',' ',(string)$en->category)) }}</div>
                            </td>
                            <td data-label="Status">
                                <span class="hims-badge {{ $en->status==='completed'?'green':($en->status==='in_progress'?'yellow':'gray') }}">
                                    {{ ucfirst(str_replace('_',' ',(string)$en->status)) }}
                                </span>
                            </td>
                            <td data-label="Progress" class="text-center">{{ (int)$en->progress_pct }}%</td>
                            <td data-label="CPD" class="text-center">{{ (float)$en->cpd_hours }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">No course enrollments.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="hims-card" style="height:100%">
            <div class="card-header"><h5><i class="bi bi-calendar3"></i> Training Sessions</h5></div>
            <div class="card-body" style="padding:0">
                @if($trainings->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th style="width:110px">Date</th>
                            <th style="width:100px">Status</th>
                            <th class="text-center" style="width:60px">CPD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trainings as $tr)
                        <tr>
                            <td data-label="Session"><strong>{{ $tr->title }}</strong></td>
                            <td data-label="Date">{{ $tr->session_date ? \Carbon\Carbon::parse($tr->session_date)->format('d M Y') : '—' }}</td>
                            <td data-label="Status">
                                <span class="hims-badge {{ $tr->status==='attended'?'green':($tr->status==='registered'?'gray':'yellow') }}">
                                    {{ ucfirst((string)$tr->status) }}
                                </span>
                            </td>
                            <td data-label="CPD" class="text-center">{{ (float)$tr->cpd_hours }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">No training registrations.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- CPD summary. Controller filtered to verified hours within the last 12 months. --}}
<div class="row g-3">
    <div class="col-12">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Verified CPD Hours (Last 12 Months)</h5>
            </div>
            <div class="card-body" style="padding:0">
                @if($cpd->count())
                <table class="hims-table">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th style="width:110px">Date Earned</th>
                            <th style="width:100px">Source</th>
                            <th class="text-center" style="width:80px">Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cpd as $c)
                        <tr>
                            <td data-label="Activity"><strong>{{ $c->activity_name }}</strong></td>
                            <td data-label="Date Earned">{{ \Carbon\Carbon::parse($c->date_earned)->format('d M Y') }}</td>
                            <td data-label="Source">
                                <span class="hims-badge blue" style="font-size:11px">
                                    {{ ucfirst((string)$c->source_type) }}
                                </span>
                            </td>
                            <td data-label="Hours" class="text-center">{{ (float)$c->cpd_hours }}</td>
                        </tr>
                        @endforeach
                        <tr style="background:var(--hims-primary-pale);font-weight:600">
                            <td colspan="3" class="text-end" style="padding-right:20px">Total</td>
                            <td data-label="Hours" class="text-center">{{ number_format($cpdTotal,1) }}</td>
                        </tr>
                    </tbody>
                </table>
                @else
                <div style="padding:32px;text-align:center;color:#9ca3af">
                    No verified CPD hours recorded in the last 12 months.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
