@extends('layouts.hims')
@section('title','All Reviews')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Reviews')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">All Performance Reviews</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">{{ $reviews->total() }} total reviews across all cycles.</p>
    </div>
    <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Employee</th><th>Cycle</th><th>Type</th><th>Status</th><th>Score</th><th>Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($reviews as $r)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <div style="width:32px;height:32px;background:var(--hims-primary-xlight);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--hims-primary-dark)">
                                {{ strtoupper(substr($r->employee_name ?? 'U',0,1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px">{{ $r->employee_name }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $r->position_title }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px">{{ $r->cycle_name }}</td>
                    <td><span class="hims-badge gray">{{ ucfirst($r->review_type ?? 'standard') }}</span></td>
                    <td>
                        @php $sc = match($r->status ?? '') { 'approved','archived'=>'green','draft','self_assessment'=>'gray','ai_audit','pending_approval'=>'blue', default=>'yellow' }; @endphp
                        <span class="hims-badge {{ $sc }}">{{ ucfirst(str_replace('_',' ',$r->status ?? 'draft')) }}</span>
                    </td>
                    <td>
                        @if($r->overall_score)
                            <span style="font-weight:700;color:{{ $r->overall_score >= 4 ? 'var(--hims-primary)' : ($r->overall_score < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                                {{ number_format($r->overall_score,2) }}/5.00
                            </span>
                        @else <span style="color:#9ca3af">—</span> @endif
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($r->updated_at)->format('M d, Y') }}</td>
                    <td><a href="{{ route('performance.show', $r->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:40px">No reviews found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reviews->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection
