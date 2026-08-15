@extends('layouts.hims')
@section('title','All Reviews')
@section('page-title','Performance Management')
@section('breadcrumb','HIMS / Performance / Reviews')

@php use App\Support\ReviewStatus; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Performance Reviews</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">{{ $reviews->total() }} reviews you are part of — your own, the ones you wrote, and your direct reports'.</p>
    </div>
    <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Employee</th><th>Reviewer</th><th>Cycle</th><th>Type</th><th>Status</th><th>Score</th><th>Updated</th><th>Actions</th></tr>
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
                    <td style="font-size:13px">{{ trim($r->reviewer_name ?? '') ?: '—' }}</td>
                    <td style="font-size:13px">{{ $r->cycle_name }}</td>
                    <td><span class="hims-badge gray">{{ ucfirst($r->review_type ?? 'standard') }}</span></td>
                    <td>
                        <span class="hims-badge {{ ReviewStatus::badgeClass($r->effective_status) }}">{{ ReviewStatus::label($r->effective_status) }}</span>
                        @if($r->is_exception_review)
                        <span class="hims-badge yellow" style="margin-left:4px" title="{{ $r->exception_reason }}">
                            <i class="bi bi-shield-exclamation"></i> {{ str_replace('_',' ',$r->exception_basis) }}
                        </span>
                        @endif
                    </td>
                    <td>
                        @if($r->overall_score)
                            <span style="font-weight:700;color:{{ $r->overall_score >= 4 ? 'var(--hims-primary)' : ($r->overall_score < 2.5 ? 'var(--hims-danger)' : '#d97706') }}">
                                {{ number_format($r->overall_score,2) }}/5.00
                            </span>
                        @else <span style="color:#9ca3af">—</span> @endif
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($r->updated_at)->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('performance.show', $r->review_id) }}" class="btn-hims btn-hims-ghost btn-sm">View</a>
                        @if($r->can_score)
                        <a href="{{ route('performance.reviews.score', $r->review_id) }}" class="btn-hims btn-hims-outline btn-sm">Score</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center" style="color:#9ca3af;padding:40px">
                    No reviews involve you yet. Reviews follow the reporting line — you see your own, the ones you wrote, and your direct reports'.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reviews->hasPages())
    <div style="padding:16px 22px;border-top:1px solid var(--hims-border)">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection
