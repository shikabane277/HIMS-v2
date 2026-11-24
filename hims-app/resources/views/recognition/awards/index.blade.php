@extends('layouts.hims')
@section('title','Awards & Badges')
@section('page-title','Social Recognition')
@section('breadcrumb','HIMS / Recognition / Awards & Badges')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4" style="flex-wrap:wrap">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Awards, Digital Certificates &amp; Badges</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Formal recognition with a verifiable certificate for every award. Service milestones and CPD achievement are awarded automatically; HR issues the rest.
        </p>
    </div>
    @if($canManage)
    <div class="d-flex gap-2" style="flex-wrap:wrap">
        <form method="POST" action="{{ route('recognition.awards.run-auto') }}" style="margin:0">@csrf
            <button class="btn-hims btn-hims-outline" title="Issue any service-milestone or CPD awards that are due"><i class="bi bi-magic"></i> Run automatic awards</button>
        </form>
        <button type="button" class="btn-hims btn-hims-outline" data-modal-open="awardTypeModal" onclick="himsAwardTypeForm()"><i class="bi bi-plus-square"></i> New award type</button>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="awardIssueModal"><i class="bi bi-award"></i> Issue award</button>
    </div>
    @endif
</div>

@include('partials.recognition-tabs')

@if(session('success'))
    <div class="hims-alert success mb-3" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="hims-alert error mb-3" data-auto-dismiss><i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}</div>
@endif
@if($errors->any() && ! old('_modal'))
    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-award"></i></div><div class="stat-value">{{ $stats['issued'] }}</div><div class="stat-label">Awards issued</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-calendar-check"></i></div><div class="stat-value">{{ $stats['this_year'] }}</div><div class="stat-label">This year</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-magic"></i></div><div class="stat-value">{{ $stats['auto'] }}</div><div class="stat-label">Given automatically</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-collection"></i></div><div class="stat-value">{{ $stats['types'] }}</div><div class="stat-label">Active award types</div></div></div>
</div>

{{-- My badges --}}
<div class="hims-card mb-4">
    <div class="card-header"><h5><i class="bi bi-person-badge"></i> My badges</h5><span class="hims-badge green">{{ $mine->count() }}</span></div>
    <div class="card-body">
        @forelse($mine as $a)
            @if($loop->first)<div class="award-badges">@endif
            <a href="{{ route('awards.verify', $a->certificate_code) }}" class="award-badge" title="{{ $a->award_name }} — {{ \Carbon\Carbon::parse($a->awarded_on)->format('M d, Y') }}">
                <span class="award-badge-disc" style="--c: {{ $a->badge_color }}"><i class="bi {{ $a->badge_icon }}"></i></span>
                <span class="award-badge-name">{{ $a->award_name }}</span>
                <span class="award-badge-date">{{ \Carbon\Carbon::parse($a->awarded_on)->format('M Y') }}{{ $a->period_label ? ' · '.$a->period_label : '' }}</span>
            </a>
            @if($loop->last)</div>@endif
        @empty
            <p style="margin:0;color:#6b7280;font-size:13px">No awards yet. Service milestones are awarded automatically on your work anniversaries, and the Lifelong Learner badge when you reach 40 qualified CPD hours in a year.</p>
        @endforelse
    </div>
</div>

<div class="row g-3">
    {{-- Recently awarded --}}
    <div class="col-xl-7">
        <div class="hims-card">
            <div class="card-header" style="flex-wrap:wrap;gap:8px">
                <h5><i class="bi bi-stars"></i> {{ request('status') === 'revoked' ? 'Revoked awards' : 'Recently awarded' }}</h5>
                <form method="GET" class="d-flex gap-2" style="margin:0">
                    <select name="category" class="hims-input" style="padding:6px 10px;font-size:12.5px;width:auto" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($canManage)
                    <select name="status" class="hims-input" style="padding:6px 10px;font-size:12.5px;width:auto" onchange="this.form.submit()">
                        <option value="">Issued</option>
                        <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                    </select>
                    @endif
                </form>
            </div>
            <div class="card-body" style="padding:0">
                <table class="hims-table">
                    <thead><tr><th>Employee</th><th>Award</th><th>Date</th><th>Certificate</th>@if($canManage)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse($recent as $a)
                        <tr>
                            <td data-label="Employee"><strong>{{ $a->employee_name }}</strong><div style="font-size:11.5px;color:#6b7280">{{ $a->department_name }}</div></td>
                            <td data-label="Award">
                                <span class="award-chip" style="--c: {{ $a->badge_color }}"><i class="bi {{ $a->badge_icon }}"></i> {{ $a->award_name }}</span>
                                @if($a->source === 'auto')<span class="hims-badge gray" title="Issued automatically">auto</span>@endif
                                @if($a->citation)<div style="font-size:11.5px;color:#6b7280;margin-top:3px">{{ \Illuminate\Support\Str::limit($a->citation, 90) }}</div>@endif
                                @if($a->status === 'revoked')<div style="font-size:11.5px;color:#dc2626">Revoked: {{ $a->revoked_reason }}</div>@endif
                            </td>
                            <td data-label="Date" style="font-size:12.5px;color:#6b7280;white-space:nowrap">{{ \Carbon\Carbon::parse($a->awarded_on)->format('M d, Y') }}</td>
                            <td data-label="Certificate"><a href="{{ route('awards.verify', $a->certificate_code) }}" target="_blank" class="btn-hims btn-hims-ghost btn-sm"><i class="bi bi-file-earmark-richtext"></i> {{ $a->certificate_code }}</a></td>
                            @if($canManage)
                            <td data-label="Actions">
                                @if($a->status === 'issued')
                                <form method="POST" action="{{ route('recognition.awards.revoke', $a->award_id) }}" style="margin:0" onsubmit="var r = prompt('Reason for revoking this award (shown on the certificate):'); if (!r || r.trim().length < 5) { return false; } this.reason.value = r; return true;">
                                    @csrf <input type="hidden" name="reason">
                                    <button class="btn-hims btn-hims-ghost btn-sm" style="color:var(--hims-danger)" title="Revoke"><i class="bi bi-x-octagon"></i></button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="{{ $canManage ? 5 : 4 }}" class="text-center" style="color:#9ca3af;padding:40px">No awards in this view yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recent->hasPages())<div style="padding:14px 20px;border-top:1px solid var(--hims-border)">{{ $recent->links() }}</div>@endif
        </div>
    </div>

    {{-- Catalogue --}}
    <div class="col-xl-5">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-journal-bookmark"></i> Award catalogue</h5></div>
            <div class="card-body" style="display:grid;gap:10px">
                @foreach($types as $t)
                <div class="award-type {{ $t->is_active ? '' : 'is-retired' }}">
                    <span class="award-badge-disc sm" style="--c: {{ $t->badge_color }}"><i class="bi {{ $t->badge_icon }}"></i></span>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:14px">{{ $t->name }}
                            @unless($t->is_active)<span class="hims-badge gray">retired</span>@endunless
                            @if($t->auto_rule)<span class="hims-badge blue" title="{{ $autoRules[$t->auto_rule] ?? '' }}"><i class="bi bi-magic"></i> auto</span>@endif
                        </div>
                        <div style="font-size:11.5px;color:#6b7280">{{ $categories[$t->category] ?? ucfirst($t->category) }} · {{ $t->points_value }} pts · {{ (int) $t->issued_count }} issued</div>
                        @if($t->criteria)<div style="font-size:12px;color:#374151;margin-top:3px">{{ $t->criteria }}</div>@endif
                    </div>
                    @if($canManage)
                    <div class="d-flex flex-column gap-1">
                        <button type="button" class="btn-hims btn-hims-ghost btn-sm" data-modal-open="awardTypeModal" onclick='himsAwardTypeForm(@json($t))' title="Edit"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('recognition.awards.types.toggle', $t->award_type_id) }}" style="margin:0">@csrf
                            <button class="btn-hims btn-hims-ghost btn-sm" title="{{ $t->is_active ? 'Retire' : 'Reactivate' }}"><i class="bi {{ $t->is_active ? 'bi-archive' : 'bi-arrow-counterclockwise' }}"></i></button>
                        </form>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    .award-badges { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 14px; }
    .award-badge { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; text-decoration: none; color: inherit; padding: 10px 6px; border-radius: 12px; transition: background .15s; }
    .award-badge:hover { background: var(--hims-primary-pale); }
    .award-badge-disc { --c: #16a34a; width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 26px;
        background: radial-gradient(circle at 30% 25%, color-mix(in srgb, var(--c) 70%, #fff), var(--c)); box-shadow: 0 0 0 4px color-mix(in srgb, var(--c) 22%, #fff), 0 6px 14px rgba(0,0,0,.12); }
    .award-badge-disc.sm { width: 42px; height: 42px; font-size: 18px; flex: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 22%, #fff); }
    .award-badge-name { font-weight: 700; font-size: 12.5px; line-height: 1.25; }
    .award-badge-date { font-size: 11px; color: #6b7280; }
    .award-chip { --c: #16a34a; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; color: var(--c); }
    .award-type { display: flex; gap: 12px; align-items: flex-start; padding: 10px; border: 1px solid var(--hims-border); border-radius: 10px; }
    .award-type.is-retired { opacity: .55; }
</style>
@endpush

@if($canManage)
@push('modals')
{{-- Issue award --}}
<div class="hims-modal-backdrop" id="awardIssueModal" role="dialog" aria-modal="true" aria-labelledby="awardIssueTitle">
    <div class="hims-modal" style="max-width:680px">
        <div class="hims-modal-header">
            <h5 id="awardIssueTitle"><i class="bi bi-award"></i> Issue award</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('recognition.awards.issue') }}">
            @csrf
            <input type="hidden" name="_modal" value="awardIssueModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'awardIssueModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="hims-label" for="award_type_id">Award *</label>
                        <select name="award_type_id" id="award_type_id" class="hims-input" required>
                            <option value="">Choose an award…</option>
                            @foreach($types->where('is_active', true) as $t)
                                <option value="{{ $t->award_type_id }}" @selected(old('award_type_id') === $t->award_type_id)>{{ $t->name }}{{ $t->auto_rule ? ' (normally automatic)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="hims-label" for="awarded_on">Award date *</label>
                        <input type="date" name="awarded_on" id="awarded_on" class="hims-input" required max="{{ now()->toDateString() }}" value="{{ old('awarded_on', now()->toDateString()) }}">
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="award_employee_filter">Recipients * <span style="font-weight:400;color:#6b7280">(tick one person, or a whole team)</span></label>
                        <input type="search" id="award_employee_filter" class="hims-input mb-2" placeholder="Filter by name, code or department…" data-checklist-filter="award_employee_ids">
                        <div class="hims-checklist" id="award_employee_ids" style="max-height:220px">
                            @foreach($employees as $e)
                                <label>
                                    <input type="checkbox" name="employee_ids[]" value="{{ $e->employee_id }}" @checked(in_array($e->employee_id, (array) old('employee_ids', []), true))>
                                    <span>{{ $e->last_name }}, {{ $e->first_name }} · {{ $e->employee_code }} · {{ $e->department_name }}</span>
                                </label>
                            @endforeach
                            <div class="hims-checklist-empty" data-checklist-empty style="display:none">No employee matches that filter.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="hims-label" for="period_label">Period</label>
                        <input type="text" name="period_label" id="period_label" class="hims-input" maxlength="40" placeholder="e.g. Q4 2026" value="{{ old('period_label') }}">
                    </div>
                    <div class="col-md-8">
                        <label class="hims-label" for="citation">Citation <span style="font-weight:400;color:#6b7280">(printed on the certificate)</span></label>
                        <textarea name="citation" id="citation" class="hims-input" rows="3" maxlength="1000" placeholder="What did they do? Be specific.">{{ old('citation') }}</textarea>
                    </div>
                </div>
                <p style="font-size:12px;color:#6b7280;margin:12px 0 0"><i class="bi bi-info-circle"></i> Each recipient gets a certificate with a unique verification code, a badge on their profile and a notification.</p>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-send-check"></i> Issue &amp; generate certificates</button>
            </div>
        </form>
    </div>
</div>

{{-- Create / edit award type --}}
<div class="hims-modal-backdrop" id="awardTypeModal" role="dialog" aria-modal="true" aria-labelledby="awardTypeTitle">
    <div class="hims-modal" style="max-width:720px">
        <div class="hims-modal-header">
            <h5 id="awardTypeTitle"><i class="bi bi-plus-square"></i> <span id="awardTypeTitleText">New award type</span></h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <form method="POST" id="awardTypeForm" action="{{ route('recognition.awards.types.store') }}" data-store="{{ route('recognition.awards.types.store') }}" data-update="{{ route('recognition.awards.types.update', '__ID__') }}">
            @csrf
            <input type="hidden" name="_method" id="awardTypeMethod" value="POST">
            <input type="hidden" name="_modal" value="awardTypeModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'awardTypeModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-md-7"><label class="hims-label" for="at_name">Award name *</label><input type="text" name="name" id="at_name" class="hims-input" required maxlength="120" value="{{ old('name') }}"></div>
                    <div class="col-md-5"><label class="hims-label" for="at_category">Category *</label>
                        <select name="category" id="at_category" class="hims-input" required>
                            @foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="hims-label" for="at_description">Description</label><input type="text" name="description" id="at_description" class="hims-input" maxlength="1000" value="{{ old('description') }}"></div>
                    <div class="col-12"><label class="hims-label" for="at_criteria">Eligibility criteria</label><textarea name="criteria" id="at_criteria" class="hims-input" rows="2" maxlength="2000">{{ old('criteria') }}</textarea></div>
                    <div class="col-md-4"><label class="hims-label" for="at_badge_icon">Badge icon</label><input type="text" name="badge_icon" id="at_badge_icon" class="hims-input" maxlength="50" placeholder="bi-award" value="{{ old('badge_icon', 'bi-award') }}"><div style="font-size:11px;color:#6b7280">Any Bootstrap Icons name, e.g. bi-heart-pulse</div></div>
                    <div class="col-md-2"><label class="hims-label" for="at_badge_color">Colour</label><input type="color" name="badge_color" id="at_badge_color" class="hims-input" style="height:42px;padding:4px" value="{{ old('badge_color', '#16a34a') }}"></div>
                    <div class="col-md-3"><label class="hims-label" for="at_points">Points *</label><input type="number" name="points_value" id="at_points" class="hims-input" required min="0" max="500" value="{{ old('points_value', 20) }}"></div>
                    <div class="col-md-3"><label class="hims-label">Preview</label><div><span class="award-badge-disc sm" id="at_preview" style="--c: #16a34a"><i class="bi bi-award"></i></span></div></div>
                    <div class="col-md-6"><label class="hims-label" for="at_certificate_title">Certificate title *</label><input type="text" name="certificate_title" id="at_certificate_title" class="hims-input" required maxlength="120" value="{{ old('certificate_title', 'Certificate of Recognition') }}"></div>
                    <div class="col-md-3"><label class="hims-label" for="at_signatory_name">Signatory</label><input type="text" name="signatory_name" id="at_signatory_name" class="hims-input" maxlength="120" value="{{ old('signatory_name') }}"></div>
                    <div class="col-md-3"><label class="hims-label" for="at_signatory_title">Signatory title</label><input type="text" name="signatory_title" id="at_signatory_title" class="hims-input" maxlength="120" value="{{ old('signatory_title', 'Chief Human Resources Officer') }}"></div>
                    <div class="col-12"><label class="hims-label" for="at_certificate_body">Certificate wording</label><textarea name="certificate_body" id="at_certificate_body" class="hims-input" rows="2" maxlength="2000">{{ old('certificate_body') }}</textarea><div style="font-size:11px;color:#6b7280">Placeholders: {name} {award} {years} {date} {period} {citation} {cpd_hours}</div></div>
                    <div class="col-md-7"><label class="hims-label" for="at_auto_rule">Award automatically when…</label>
                        <select name="auto_rule" id="at_auto_rule" class="hims-input">
                            <option value="">Never — HR issues it</option>
                            @foreach($autoRules as $key => $label)<option value="{{ $key }}" @selected(old('auto_rule') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-5"><label class="hims-label" for="at_auto_value">Threshold (years / hours)</label><input type="number" name="auto_value" id="at_auto_value" class="hims-input" min="1" max="60" value="{{ old('auto_value') }}"></div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save award type</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function himsAwardTypeForm(t) {
    var f = document.getElementById('awardTypeForm');
    var set = function (id, v) { var el = document.getElementById(id); if (el) { el.value = v == null ? '' : v; } };
    if (t) {
        f.action = f.dataset.update.replace('__ID__', t.award_type_id);
        document.getElementById('awardTypeMethod').value = 'PUT';
        document.getElementById('awardTypeTitleText').textContent = 'Edit ' + t.name;
    } else {
        f.action = f.dataset.store;
        document.getElementById('awardTypeMethod').value = 'POST';
        document.getElementById('awardTypeTitleText').textContent = 'New award type';
        t = { badge_icon: 'bi-award', badge_color: '#16a34a', points_value: 20, certificate_title: 'Certificate of Recognition', signatory_title: 'Chief Human Resources Officer', category: 'excellence' };
    }
    ['name','category','description','criteria','badge_icon','badge_color','certificate_title','signatory_name','signatory_title','certificate_body','auto_rule','auto_value'].forEach(function (k) { set('at_' + k, t[k]); });
    set('at_points', t.points_value);
    himsAwardPreview();
}
function himsAwardPreview() {
    var p = document.getElementById('at_preview'); if (!p) { return; }
    var icon = (document.getElementById('at_badge_icon').value || 'bi-award').replace(/[^a-z0-9-]/g, '');
    p.style.setProperty('--c', document.getElementById('at_badge_color').value || '#16a34a');
    p.innerHTML = '<i class="bi ' + icon + '"></i>';
}
document.addEventListener('input', function (e) { if (e.target && (e.target.id === 'at_badge_icon' || e.target.id === 'at_badge_color')) { himsAwardPreview(); } });
</script>
@endpush

@include('partials.modal-js')
@include('partials.checklist-js')
@if($errors->any() && in_array(old('_modal'), ['awardIssueModal', 'awardTypeModal'], true))
@push('scripts')<script>window.himsModal.open(@json(old('_modal')));</script>@endpush
@endif
@endif
@endsection
