@extends('layouts.hims')
@section('title','CPD Records')
@section('page-title','Learning Management')
@section('breadcrumb','HIMS / Learning / CPD Log')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">CPD Records & Certificates</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Full continuing professional development activity log.</p>
    </div>
    <a href="{{ route('learning.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Employee</th><th>Activity</th><th>Source</th><th>Hours</th><th>Date</th><th>Verified By</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($records ?? [] as $cpd)
                <tr>
                    <td><strong>{{ $cpd->employee_name ?? '—' }}</strong></td>
                    <td>{{ $cpd->activity_name }}</td>
                    <td><span class="hims-badge {{ $cpd->source_type === 'course' ? 'blue' : 'gray' }}">{{ ucfirst(str_replace('_',' ',$cpd->source_type)) }}</span></td>
                    <td><strong>{{ $cpd->cpd_hours }}</strong> hrs</td>
                    <td style="font-size:12.5px;color:#6b7280">{{ \Carbon\Carbon::parse($cpd->date_earned)->format('M d, Y') }}</td>
                    <td style="font-size:12.5px">{{ $cpd->verified_by_name ?? '—' }}</td>
                    <td><span class="hims-badge {{ $cpd->verified ? 'green' : 'yellow' }}">{{ $cpd->verified ? '✓ Verified' : 'Pending' }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:48px">
                    <div style="font-size:36px;margin-bottom:10px">📋</div>
                    No CPD records yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($records) && method_exists($records,'hasPages') && $records->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $records->links() }}</div>
    @endif
</div>
@endsection
