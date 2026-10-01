@extends('layouts.hims')
@section('title', 'KPI Library')
@section('page-title', 'KPI Library')
@section('breadcrumb', 'HIMS / Performance / KPI Library')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Key Performance Indicators</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Standardized metrics used to evaluate clinical and administrative staff performance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('performance.index') }}" class="btn-hims btn-hims-outline">
            <i class="bi bi-arrow-left"></i> Review Cycles
        </a>
        <button type="button" class="btn-hims btn-hims-primary" data-modal-open="addKpiModal">
            <i class="bi bi-plus-circle"></i> Add KPI
        </button>
    </div>
</div>

{{-- Filters --}}
<div class="hims-card mb-4">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="{{ route('performance.kpis.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="hims-search-input" style="position:relative">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af"></i>
                    <input type="text" name="search" value="{{ $search }}" class="hims-input" style="padding-left:36px" placeholder="Search KPI name or description...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="hims-input hims-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn-hims btn-hims-outline w-100"><i class="bi bi-funnel"></i> Filter</button>
                @if($search || $category || $status)
                    <a href="{{ route('performance.kpis.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-x-circle"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- KPI Table --}}
<div class="hims-card">
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr>
                    <th>KPI Name</th>
                    <th>Category</th>
                    <th>Target & Unit</th>
                    <th>Weight</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kpis as $kpi)
                <tr>
                    <td data-label="KPI Name">
                        <strong style="color:var(--hims-text-dark)">{{ $kpi->kpi_name }}</strong>
                        @if($kpi->description)
                            <div style="font-size:12px;color:#6b7280;margin-top:2px;max-width:480px">{{ $kpi->description }}</div>
                        @endif
                    </td>
                    <td data-label="Category">
                        <span class="hims-badge blue">{{ ucfirst($kpi->kpi_category) }}</span>
                    </td>
                    <td data-label="Target & Unit">
                        @if($kpi->target_value !== null)
                            <strong>{{ $kpi->target_value }}</strong> <span style="font-size:12px;color:#6b7280">{{ $kpi->unit ?? '' }}</span>
                        @else
                            <span style="color:#9ca3af">—</span>
                        @endif
                    </td>
                    <td data-label="Weight">
                        <span style="font-family:monospace;font-weight:600">{{ number_format($kpi->weight ?? 1.0, 2) }}</span>
                    </td>
                    <td data-label="Status">
                        @if($kpi->is_active ?? true)
                            <span class="hims-badge green">Active</span>
                        @else
                            <span class="hims-badge gray">Inactive</span>
                        @endif
                    </td>
                    <td data-label="Actions" class="text-end">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn-hims btn-hims-ghost btn-sm"
                                    data-modal-open="editKpiModal"
                                    data-kpi-edit
                                    data-action="{{ route('performance.kpis.update', $kpi->kpi_id) }}"
                                    data-name="{{ $kpi->kpi_name }}"
                                    data-category="{{ $kpi->kpi_category }}"
                                    data-description="{{ $kpi->description ?? '' }}"
                                    data-target="{{ $kpi->target_value ?? '' }}"
                                    data-unit="{{ $kpi->unit ?? '' }}"
                                    data-weight="{{ $kpi->weight ?? 1.0 }}"
                                    data-active="{{ ($kpi->is_active ?? true) ? '1' : '0' }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('performance.kpis.toggle-status', $kpi->kpi_id) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn-hims btn-sm {{ ($kpi->is_active ?? true) ? 'btn-hims-ghost' : 'btn-hims-outline' }}"
                                        onclick="return confirm('{{ ($kpi->is_active ?? true) ? 'Deactivate' : 'Reactivate' }} this KPI?')">
                                    <i class="bi bi-power"></i> {{ ($kpi->is_active ?? true) ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding:48px;color:#9ca3af">
                        <i class="bi bi-card-checklist" style="font-size:32px;display:block;margin-bottom:8px"></i>
                        No KPIs found matching your criteria.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($kpis->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--hims-border)">
        {{ $kpis->links() }}
    </div>
    @endif
</div>

@push('modals')
{{-- Add KPI Modal --}}
<div class="hims-modal-backdrop" id="addKpiModal">
    <div class="hims-modal" style="max-width:520px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-plus-circle"></i> Add New KPI</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" action="{{ route('performance.kpis.store') }}">
            @csrf
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">KPI Name *</label>
                    <input type="text" name="kpi_name" class="hims-input" required placeholder="e.g. Patient Triage Response Time" maxlength="200">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="hims-label">Category *</label>
                        <select name="kpi_category" class="hims-input hims-select" required>
                            <option value="clinical">Clinical Excellence</option>
                            <option value="operational">Operational Efficiency</option>
                            <option value="safety">Patient Safety & Quality</option>
                            <option value="service">Service & Patient Care</option>
                            <option value="leadership">Leadership & Teamwork</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="hims-label">Evaluation Weight</label>
                        <input type="number" step="0.05" min="0.1" max="5.0" name="weight" class="hims-input" value="1.00">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="hims-label">Target Value</label>
                        <input type="number" step="any" name="target_value" class="hims-input" placeholder="e.g. 95 or 15">
                    </div>
                    <div class="col-sm-6">
                        <label class="hims-label">Unit of Measure</label>
                        <input type="text" name="unit" class="hims-input" placeholder="e.g. %, minutes, cases" maxlength="30">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Description / Measurement Protocol</label>
                    <textarea name="description" class="hims-input" rows="3" placeholder="How this metric is observed and evaluated..."></textarea>
                </div>
                <div class="mb-2">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;font-weight:600">
                        <input type="checkbox" name="is_active" value="1" checked> Active in Review Cycle Templates
                    </label>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save KPI</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit KPI Modal --}}
<div class="hims-modal-backdrop" id="editKpiModal">
    <div class="hims-modal" style="max-width:520px">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Edit KPI</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="editKpiForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">KPI Name *</label>
                    <input type="text" name="kpi_name" id="edit_kpi_name" class="hims-input" required maxlength="200">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="hims-label">Category *</label>
                        <select name="kpi_category" id="edit_kpi_category" class="hims-input hims-select" required>
                            <option value="clinical">Clinical Excellence</option>
                            <option value="operational">Operational Efficiency</option>
                            <option value="safety">Patient Safety & Quality</option>
                            <option value="service">Service & Patient Care</option>
                            <option value="leadership">Leadership & Teamwork</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="hims-label">Evaluation Weight</label>
                        <input type="number" step="0.05" min="0.1" max="5.0" name="weight" id="edit_kpi_weight" class="hims-input">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="hims-label">Target Value</label>
                        <input type="number" step="any" name="target_value" id="edit_kpi_target" class="hims-input">
                    </div>
                    <div class="col-sm-6">
                        <label class="hims-label">Unit of Measure</label>
                        <input type="text" name="unit" id="edit_kpi_unit" class="hims-input" maxlength="30">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Description / Measurement Protocol</label>
                    <textarea name="description" id="edit_kpi_description" class="hims-input" rows="3"></textarea>
                </div>
                <div class="mb-2">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;font-weight:600">
                        <input type="checkbox" name="is_active" id="edit_kpi_active" value="1"> Active in Review Cycle Templates
                    </label>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-kpi-edit]');
    if (!btn) return;
    const form = document.getElementById('editKpiForm');
    form.action = btn.dataset.action;
    document.getElementById('edit_kpi_name').value = btn.dataset.name;
    document.getElementById('edit_kpi_category').value = btn.dataset.category;
    document.getElementById('edit_kpi_description').value = btn.dataset.description;
    document.getElementById('edit_kpi_target').value = btn.dataset.target;
    document.getElementById('edit_kpi_unit').value = btn.dataset.unit;
    document.getElementById('edit_kpi_weight').value = btn.dataset.weight;
    document.getElementById('edit_kpi_active').checked = btn.dataset.active === '1';
});
</script>
@endpush

@endsection
