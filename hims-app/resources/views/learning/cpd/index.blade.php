@extends('layouts.hims')
@section('title','CPD Records')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / CPD Log')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">CPD Records &amp; Certificates</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Continuing professional development log. An entry <strong>qualifies</strong> only when HIMS has
            evidence: a completed course, recorded attendance at a training session, or an external
            certificate checked by HR. Only qualified hours count toward renewal cycles.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('learning.cycles.mine') }}" class="btn-hims btn-hims-outline"><i class="bi bi-arrow-repeat"></i> My Renewal Cycles</a>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="cpdCreateModal">
            <i class="bi bi-plus-circle"></i> Record CPD
        </button>
    </div>
</div>

@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3" data-auto-dismiss><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Hours Claimed</div>
            <div style="font-size:26px;font-weight:700">{{ rtrim(rtrim(number_format((float) ($totals->total_hours ?? 0), 1), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Qualified Hours</div>
            <div style="font-size:26px;font-weight:700;color:#059669">{{ rtrim(rtrim(number_format((float) ($totals->verified_hours ?? 0), 1), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Not Qualified</div>
            <div style="font-size:26px;font-weight:700;color:{{ ($totals->not_qualified_count ?? 0) > 0 ? '#dc2626' : 'inherit' }}">{{ $totals->not_qualified_count ?? 0 }}</div>
            <div style="font-size:11.5px;color:#6b7280">no attendance / completion on record</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Pending HR Check</div>
            <div style="font-size:26px;font-weight:700;color:{{ ($totals->pending_count ?? 0) > 0 ? '#d97706' : 'inherit' }}">{{ $totals->pending_count ?? 0 }}</div>
            <div style="font-size:11.5px;color:#6b7280">external certificates</div>
        </div></div>
    </div>
</div>

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('learning.cpd.index') }}"
       class="btn-hims {{ $filter === 'all' ? 'btn-hims-primary' : 'btn-hims-outline' }}">All</a>
    <a href="{{ route('learning.cpd.index', ['filter' => 'qualified']) }}"
       class="btn-hims {{ $filter === 'qualified' ? 'btn-hims-primary' : 'btn-hims-outline' }}">Qualified</a>
    <a href="{{ route('learning.cpd.index', ['filter' => 'not_qualified']) }}"
       class="btn-hims {{ $filter === 'not_qualified' ? 'btn-hims-primary' : 'btn-hims-outline' }}">Not qualified</a>
    <a href="{{ route('learning.cpd.index', ['filter' => 'pending']) }}"
       class="btn-hims {{ $filter === 'pending' ? 'btn-hims-primary' : 'btn-hims-outline' }}">Pending HR check</a>
</div>

<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Activity</th><th>Source</th><th>Hours</th>
                    <th>Date</th><th>Verified By</th><th>Qualification</th>
                    @if($canVerify)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($records ?? [] as $cpd)
                {{-- Global search deep-links a CPD hit to #cpd-<id>; the anchor
                     has to sit on the record's own row. --}}
                <tr id="cpd-{{ $cpd->cpd_id }}" style="scroll-margin-top:84px">
                    <td data-label="Employee"><strong>{{ $cpd->employee_name ?? '—' }}</strong></td>
                    <td data-label="Activity">{{ $cpd->activity_name }}</td>
                    <td data-label="Source"><span class="hims-badge {{ $cpd->source_type === 'external' ? 'gray' : 'blue' }}">{{ ucfirst(str_replace('_',' ',$cpd->source_type)) }}</span></td>
                    <td data-label="Hours"><strong>{{ $cpd->cpd_hours }}</strong> hrs</td>
                    <td data-label="Date" style="font-size:12.5px;color:#6b7280">{{ \Carbon\Carbon::parse($cpd->date_earned)->format('M d, Y') }}</td>
                    <td data-label="Verified By" style="font-size:12.5px">{{ trim($cpd->verified_by_name ?? '') ?: '—' }}</td>
                    @php $q = $qualification[$cpd->cpd_id] ?? null; @endphp
                    <td data-label="Qualification" style="max-width:280px">
                        @if($q)
                            <span class="hims-badge {{ $q['badge'] }}"><i class="bi {{ $q['icon'] }}"></i> {{ $q['label'] }}</span>
                            <div style="font-size:11.5px;color:#6b7280;margin-top:3px">{{ $q['reason'] }}</div>
                            @if($q['status'] === 'qualified' && (float) $q['eligible_hours'] !== (float) $cpd->cpd_hours)
                                <div style="font-size:11.5px;color:#059669">{{ $q['eligible_hours'] }} h counted</div>
                            @endif
                        @endif
                    </td>
                    @if($canVerify)
                    <td data-label="Actions">
                        @if(! $cpd->verified && $cpd->source_type === 'external')
                            <form method="POST" action="{{ route('learning.cpd.verify', $cpd->cpd_id) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn-hims btn-hims-outline" style="padding:4px 10px;font-size:12px">Verify certificate</button>
                            </form>
                        @endif
                    </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $canVerify ? 8 : 7 }}" class="text-center" style="color:#9ca3af;padding:48px">
                    <div style="font-size:36px;margin-bottom:10px">📋</div>
                    {{ match($filter) { 'pending' => 'Nothing awaiting HR verification.', 'qualified' => 'No qualified CPD yet.', 'not_qualified' => 'Every course / training entry has evidence on record.', default => 'No CPD records yet.' } }}
                    @if($filter === 'all')
                    <div style="margin-top:8px">
                        <button type="button" class="hims-link-button text-primary-hims" data-modal-open="cpdCreateModal">Record the first one</button>.
                    </div>
                    @endif
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($records) && method_exists($records,'hasPages') && $records->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $records->links() }}</div>
    @endif
</div>

@include('learning.cpd._create-modal')
@endsection
