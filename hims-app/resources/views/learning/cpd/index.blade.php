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
            Continuing professional development activity log. Hours only count toward a renewal
            cycle once they are verified.
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
    <div class="col-md-4">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Total Hours</div>
            <div style="font-size:26px;font-weight:700">{{ rtrim(rtrim(number_format((float) ($totals->total_hours ?? 0), 1), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Verified Hours</div>
            <div style="font-size:26px;font-weight:700;color:#059669">{{ rtrim(rtrim(number_format((float) ($totals->verified_hours ?? 0), 1), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="hims-card"><div class="card-body">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Pending Verification</div>
            <div style="font-size:26px;font-weight:700;color:{{ ($totals->pending_count ?? 0) > 0 ? '#d97706' : 'inherit' }}">{{ $totals->pending_count ?? 0 }}</div>
        </div></div>
    </div>
</div>

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('learning.cpd.index') }}"
       class="btn-hims {{ $filter === 'all' ? 'btn-hims-primary' : 'btn-hims-outline' }}">All</a>
    <a href="{{ route('learning.cpd.index', ['filter' => 'pending']) }}"
       class="btn-hims {{ $filter === 'pending' ? 'btn-hims-primary' : 'btn-hims-outline' }}">Pending only</a>
</div>

<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Activity</th><th>Source</th><th>Hours</th>
                    <th>Date</th><th>Verified By</th><th>Status</th>
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
                    <td data-label="Status"><span class="hims-badge {{ $cpd->verified ? 'green' : 'yellow' }}">{{ $cpd->verified ? '✓ Verified' : 'Pending' }}</span></td>
                    @if($canVerify)
                    <td data-label="Actions">
                        @unless($cpd->verified)
                            <form method="POST" action="{{ route('learning.cpd.verify', $cpd->cpd_id) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn-hims btn-hims-outline" style="padding:4px 10px;font-size:12px">Verify</button>
                            </form>
                        @endunless
                    </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $canVerify ? 8 : 7 }}" class="text-center" style="color:#9ca3af;padding:48px">
                    <div style="font-size:36px;margin-bottom:10px">📋</div>
                    {{ $filter === 'pending' ? 'Nothing awaiting verification.' : 'No CPD records yet.' }}
                    @if($filter !== 'pending')
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
