@extends('layouts.hims')
@section('title','Renewal Rules')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / Renewals / Rules')
@section('content')
@include('partials.learning-tabs')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Renewal Rules</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            How many CPD hours a credential or category requires, and over what window. Without a rule
            the CPD log is only a running total — it cannot say whether anyone is on track.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('learning.renewals.index') }}" class="btn-hims btn-hims-outline"><i class="bi bi-hourglass-split"></i> Renewal Risk</a>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="ruleCreateModal">
            <i class="bi bi-plus-circle"></i> New Rule
        </button>
    </div>
</div>

{{-- The New Rule form used to sit in a col-lg-5 beside a cramped col-lg-7 table.
     It is a modal now, so the table gets the full width it needs for five
     columns of numbers. --}}
<div class="hims-card">
    <div class="card-header">
        <h5><i class="bi bi-book"></i> Active Rules</h5>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('learning.renewals.sync') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn-hims btn-hims-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Open cycles</button>
            </form>
            <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-modal-open="ruleCreateModal">New</button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Rule</th><th>Applies To</th><th>Requirement</th><th>Cycle</th><th>Open Cycles</th></tr></thead>
            <tbody>
                @forelse($rules as $rule)
                <tr>
                    <td>
                        <div style="font-weight:600">{{ $rule->label }}</div>
                        <div style="font-size:11px;color:#9ca3af">{{ $rule->subject_key }}</div>
                    </td>
                    <td>
                        <span class="hims-badge {{ $rule->subject_type === 'credential' ? 'blue' : 'purple' }}">
                            {{ $rule->subject_type === 'credential' ? 'Credential' : 'CPD category' }}
                        </span>
                    </td>
                    <td><strong>{{ rtrim(rtrim(number_format($rule->required_hours, 1), '0'), '.') }}</strong> hrs</td>
                    <td>
                        {{ $rule->cycle_months }} months
                        @if($rule->grace_days)
                        <div style="font-size:11px;color:#9ca3af">+{{ $rule->grace_days }} day grace</div>
                        @endif
                    </td>
                    <td>{{ $rule->cycles_count }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center" style="color:#9ca3af;padding:32px">
                    No rules yet.
                    <button type="button" class="hims-link-button text-primary-hims" data-modal-open="ruleCreateModal">Add one</button>,
                    then press <strong>Open cycles</strong>.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-alert info mt-3">
    <i class="bi bi-info-circle-fill"></i>
    <strong>Open cycles</strong> walks every active employee and opens any cycle a rule implies but
    they do not have yet. It is deliberately a button rather than automatic on save — it writes a
    row per employee per rule, and a typo would be expensive to unpick.
</div>

@include('learning.renewals._rule-modal')
@endsection
