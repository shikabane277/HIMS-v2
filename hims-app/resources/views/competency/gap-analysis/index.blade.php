@extends('layouts.hims')
@section('title','AI Gap Analysis')
@section('page-title','AI-Driven Competency Gap Analysis')
@section('breadcrumb','HIMS / Competency / Gap Analysis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">AI-Driven Competency Gap Analysis</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            Combines performance results, competency assessments against job requirements, and training received
            to identify missing skills and suggest improvements.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('competency.role-requirements.index') }}" class="btn-hims btn-hims-outline">
            <i class="bi bi-sliders"></i> Role Requirements
        </a>
        <a href="{{ route('competency.gap.department', request()->only('department')) }}" class="btn-hims btn-hims-primary">
            <i class="bi bi-robot"></i> Run Department Analysis
        </a>
    </div>
</div>

<div class="hims-card mb-4" style="overflow:visible !important;position:relative;z-index:30">
    <div class="card-body" style="overflow:visible !important;position:relative;padding:18px 22px">
        <div class="row g-3 align-items-end">
            {{-- Type-to-search specific employee --}}
            <div class="col-lg-7 col-md-12">
                <form method="GET" action="{{ route('competency.gap.index') }}" id="employeeSearchForm" class="d-flex flex-column gap-1">
                    <label class="hims-label mb-1" for="employeeSearchInput" style="font-weight:600;font-size:13px;color:var(--hims-text)">
                        <i class="bi bi-person-check-fill" style="color:var(--hims-primary);margin-right:4px"></i> Select Specific Employee for AI Gap Analysis
                    </label>
                    <div class="d-flex gap-2 align-items-center" style="position:relative">
                        <div style="position:relative;flex:1">
                            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;font-size:13px"></i>
                            <input type="text"
                                   id="employeeSearchInput"
                                   class="hims-input"
                                   placeholder="Type name, position, or department to search..."
                                   style="padding:9px 36px 9px 34px;font-size:13.5px;width:100%"
                                   autocomplete="off">
                            <button type="button" id="clearEmployeeSearch"
                                    style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;line-height:1;padding:0"
                                    title="Clear">&times;</button>
                            <input type="hidden" name="employee_id" id="selectedEmployeeId" value="">

                            {{-- Floating type-to-search dropdown --}}
                            <div id="employeeSearchDropdown"
                                 style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:280px;overflow-y:auto;background:var(--hims-surface,#ffffff);border:1px solid var(--hims-border,#cbd5e1);border-radius:8px;box-shadow:0 12px 28px -4px rgba(0,0,0,0.12),0 6px 12px -4px rgba(0,0,0,0.06);z-index:9999">
                                @foreach($allEmployees ?? $employees as $emp)
                                    <div class="employee-option-item"
                                         data-id="{{ $emp->employee_id }}"
                                         data-fullname="{{ $emp->first_name }} {{ $emp->last_name }}"
                                         data-title="{{ $emp->position_title ?? 'Staff' }}"
                                         data-dept="{{ $emp->department_name }}"
                                         style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;transition:background 0.15s ease">
                                        <div>
                                            <strong style="color:var(--hims-text,#0f172a);font-size:13.5px">{{ $emp->first_name }} {{ $emp->last_name }}</strong>
                                            <div style="font-size:11.5px;color:#64748b">{{ $emp->position_title ?? 'Staff' }}</div>
                                        </div>
                                        <span class="hims-badge gray" style="font-size:11px">{{ $emp->department_name }}</span>
                                    </div>
                                @endforeach
                                <div id="noEmployeeFound" style="display:none;padding:16px;text-align:center;color:#94a3b8;font-size:12.5px">
                                    No employee found matching your search.
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn-hims btn-hims-primary text-nowrap" style="padding:9px 18px">
                            <i class="bi bi-robot"></i> Run AI Analysis
                        </button>
                    </div>
                </form>
            </div>

            {{-- Department type-to-search filter --}}
            @if($departments->isNotEmpty())
            @php
                $activeDept = $departments->firstWhere('department_id', $departmentId);
                $activeDeptName = $activeDept ? $activeDept->name : '';
            @endphp
            <div class="col-lg-5 col-md-12">
                <form method="GET" action="{{ route('competency.gap.index') }}" id="departmentSearchForm" class="d-flex flex-column gap-1">
                    <label class="hims-label mb-1" for="departmentSearchInput" style="font-weight:600;font-size:13px;color:var(--hims-text)">
                        <i class="bi bi-buildings" style="color:var(--hims-primary);margin-right:4px"></i> Select / Filter by Department
                    </label>
                    <div class="d-flex gap-2 align-items-center" style="position:relative">
                        <div style="position:relative;flex:1">
                            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;font-size:13px"></i>
                            <input type="text"
                                   id="departmentSearchInput"
                                   class="hims-input"
                                   placeholder="Type department name to search..."
                                   value="{{ $activeDeptName }}"
                                   style="padding:9px 36px 9px 34px;font-size:13.5px;width:100%"
                                   autocomplete="off">
                            <button type="button" id="clearDepartmentSearch"
                                    style="{{ $departmentId ? 'display:block' : 'display:none' }};position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;line-height:1;padding:0"
                                    title="Reset to all departments">&times;</button>
                            <input type="hidden" name="department" id="selectedDepartmentId" value="{{ $departmentId ?? '' }}">

                            {{-- Floating type-to-search dropdown for departments --}}
                            <div id="departmentSearchDropdown"
                                 style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--hims-surface,#ffffff);border:1px solid var(--hims-border,#cbd5e1);border-radius:8px;box-shadow:0 12px 28px -4px rgba(0,0,0,0.12),0 6px 12px -4px rgba(0,0,0,0.06);z-index:9999">
                                <div class="dept-option-item"
                                     data-id=""
                                     data-name="All Departments"
                                     style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-weight:{{ empty($departmentId) ? '600' : 'normal' }};color:{{ empty($departmentId) ? 'var(--hims-primary)' : 'inherit' }};transition:background 0.15s ease">
                                    <i class="bi bi-diagram-2" style="margin-right:6px"></i> — All Departments (Whole Organisation) —
                                </div>
                                @foreach($departments as $dept)
                                    <div class="dept-option-item"
                                         data-id="{{ $dept->department_id }}"
                                         data-name="{{ $dept->name }}"
                                         style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:{{ $departmentId === $dept->department_id ? '#eff6ff' : '' }};transition:background 0.15s ease">
                                        <strong style="color:var(--hims-text,#0f172a);font-size:13.5px">{{ $dept->name }}</strong>
                                        <span class="hims-badge gray" style="font-size:11px">Department</span>
                                    </div>
                                @endforeach
                                <div id="noDeptFound" style="display:none;padding:14px;text-align:center;color:#94a3b8;font-size:12.5px">
                                    No department found matching your search.
                                </div>
                            </div>
                        </div>
                        @if($departmentId)
                            <a href="{{ route('competency.gap.index') }}" class="btn-hims btn-hims-outline" style="padding:9px 14px">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Department-level weak spots (computed from the database, no AI call) --}}
<div class="hims-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-graph-down-arrow"></i> Weakest Competencies {{ $department['department']->name ?? '— whole organisation' }}</h5>
        <span class="hims-badge blue">{{ $department['headcount'] }} active staff</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead>
                <tr><th>Competency</th><th>Required</th><th>Avg Proficiency</th><th>Avg Gap</th><th>Below Requirement</th><th>Suggested Response</th></tr>
            </thead>
            <tbody>
                @forelse($department['weakest'] as $row)
                <tr>
                    <td data-label="Competency">
                        <strong>{{ $row->competency_name }}</strong>
                        @if($row->is_mandatory)<span class="hims-badge red" style="margin-left:6px">Mandatory</span>@endif
                    </td>
                    <td data-label="Required">{{ $row->required_proficiency }}/5</td>
                    <td data-label="Avg Proficiency">{{ number_format((float) $row->avg_proficiency, 2) }}</td>
                    <td data-label="Avg Gap"><span class="gap-chip negative">{{ number_format((float) $row->avg_gap, 2) }}</span></td>
                    <td data-label="Below Requirement">{{ $row->employees_below }} of {{ $row->assessed_employees }}</td>
                    <td data-label="Suggested Response" style="font-size:12px;color:#6b7280">
                        {{ abs((float) $row->avg_gap) >= 2 ? 'Instructor-led workshop with supervised practice' : 'Refresher module plus reassessment' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="color:#9ca3af;padding:32px">
                    No measured gaps. Either every assessed competency meets its requirement, or no assessments have been recorded yet.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="hims-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 style="margin:0"><i class="bi bi-person-lines-fill"></i> Analyse an Individual</h5>
            <span style="font-size:12px;color:#9ca3af">{{ $employees->count() }} employee{{ $employees->count() === 1 ? '' : 's' }} in scope</span>
        </div>
        <div style="position:relative">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none"></i>
            <input type="text" id="individualTableFilter" class="hims-input" placeholder="Type to filter table..." style="padding:5px 12px 5px 30px;font-size:12px;width:220px">
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="hims-table">
            <thead><tr><th>Employee</th><th>Position</th><th>Department</th><th class="text-end">Analysis</th></tr></thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td data-label="Employee"><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                    <td data-label="Position">{{ $employee->position_title ?? '—' }}</td>
                    <td data-label="Department">{{ $employee->department_name }}</td>
                    <td data-label="Analysis" class="text-end">
                        <a href="{{ route('competency.gap.employee', $employee->employee_id) }}" class="btn-hims btn-hims-primary btn-sm">
                            <i class="bi bi-robot"></i> Analyse
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center" style="color:#9ca3af;padding:32px">No employees in your scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Top Type-to-Search Combobox ──────────────────────────────────
    const searchInput = document.getElementById('employeeSearchInput');
    const searchDropdown = document.getElementById('employeeSearchDropdown');
    const hiddenId = document.getElementById('selectedEmployeeId');
    const clearBtn = document.getElementById('clearEmployeeSearch');
    const searchForm = document.getElementById('employeeSearchForm');

    if (searchInput && searchDropdown && searchForm) {
        const items = searchDropdown.querySelectorAll('.employee-option-item');
        const noResults = document.getElementById('noEmployeeFound');
        let highlightedIndex = -1;

        function filterDropdown(query) {
            query = (query || '').toLowerCase().trim();
            let matchCount = 0;

            items.forEach((item) => {
                const name = (item.dataset.fullname || '').toLowerCase();
                const title = (item.dataset.title || '').toLowerCase();
                const dept = (item.dataset.dept || '').toLowerCase();
                const matches = !query || name.includes(query) || title.includes(query) || dept.includes(query);

                if (matches) {
                    item.style.display = 'flex';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
                item.style.background = '';
            });

            if (noResults) noResults.style.display = matchCount === 0 ? 'block' : 'none';
            searchDropdown.style.display = 'block';
            if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';
            highlightedIndex = -1;
        }

        searchInput.addEventListener('focus', function() {
            filterDropdown(this.value);
        });

        searchInput.addEventListener('input', function() {
            hiddenId.value = '';
            filterDropdown(this.value);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                hiddenId.value = '';
                clearBtn.style.display = 'none';
                filterDropdown('');
                searchInput.focus();
            });
        }

        items.forEach(item => {
            item.addEventListener('mouseenter', function() {
                items.forEach(i => i.style.background = '');
                this.style.background = '#f1f5f9';
            });

            item.addEventListener('click', function() {
                selectItem(this);
                searchForm.submit();
            });
        });

        function selectItem(item) {
            hiddenId.value = item.dataset.id;
            searchInput.value = item.dataset.fullname + ' — ' + item.dataset.title + ' (' + item.dataset.dept + ')';
            searchDropdown.style.display = 'none';
            if (clearBtn) clearBtn.style.display = 'block';
        }

        // Keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
            if (visibleItems.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                searchDropdown.style.display = 'block';
                highlightedIndex = (highlightedIndex + 1) % visibleItems.length;
                updateHighlight(visibleItems);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                searchDropdown.style.display = 'block';
                highlightedIndex = (highlightedIndex - 1 + visibleItems.length) % visibleItems.length;
                updateHighlight(visibleItems);
            } else if (e.key === 'Enter') {
                if (highlightedIndex >= 0 && highlightedIndex < visibleItems.length) {
                    e.preventDefault();
                    selectItem(visibleItems[highlightedIndex]);
                    searchForm.submit();
                } else if (!hiddenId.value && visibleItems.length === 1) {
                    e.preventDefault();
                    selectItem(visibleItems[0]);
                    searchForm.submit();
                }
            } else if (e.key === 'Escape') {
                searchDropdown.style.display = 'none';
            }
        });

        function updateHighlight(visibleItems) {
            visibleItems.forEach((item, index) => {
                if (index === highlightedIndex) {
                    item.style.background = '#e2e8f0';
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.style.background = '';
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#employeeSearchForm')) {
                searchDropdown.style.display = 'none';
            }
        });

        // Form submission guard
        searchForm.addEventListener('submit', function(e) {
            if (!hiddenId.value) {
                const visibleItems = Array.from(items).filter(i => i.style.display !== 'none');
                if (visibleItems.length > 0) {
                    hiddenId.value = visibleItems[0].dataset.id;
                } else {
                    e.preventDefault();
                    alert('Please type and select an employee from the list.');
                    searchInput.focus();
                }
            }
        });
    }

    // ── 2. Table Real-Time Filter ───────────────────────────────────────
    const tableFilter = document.getElementById('individualTableFilter');
    if (tableFilter) {
        tableFilter.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.hims-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = (!term || text.includes(term)) ? '' : 'none';
            });
        });
    }

    // ── 3. Department Type-to-Search Combobox ───────────────────────────
    const deptInput = document.getElementById('departmentSearchInput');
    const deptDropdown = document.getElementById('departmentSearchDropdown');
    const deptHiddenId = document.getElementById('selectedDepartmentId');
    const deptClearBtn = document.getElementById('clearDepartmentSearch');
    const deptForm = document.getElementById('departmentSearchForm');

    if (deptInput && deptDropdown && deptForm) {
        const deptItems = deptDropdown.querySelectorAll('.dept-option-item');
        const noDeptResults = document.getElementById('noDeptFound');
        let deptHighlighted = -1;

        function filterDeptDropdown(query) {
            query = (query || '').toLowerCase().trim();
            let matchCount = 0;

            deptItems.forEach((item) => {
                const name = (item.dataset.name || '').toLowerCase();
                const matches = !query || name.includes(query) || (item.dataset.id === '' && 'all departments whole organisation'.includes(query));

                if (matches) {
                    item.style.display = item.dataset.id === '' ? 'block' : 'flex';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
                item.style.background = '';
            });

            if (noDeptResults) noDeptResults.style.display = matchCount === 0 ? 'block' : 'none';
            deptDropdown.style.display = 'block';
            if (deptClearBtn) deptClearBtn.style.display = query ? 'block' : 'none';
            deptHighlighted = -1;
        }

        deptInput.addEventListener('focus', function() {
            this.select();
            filterDeptDropdown('');
        });

        deptInput.addEventListener('input', function() {
            filterDeptDropdown(this.value);
        });

        if (deptClearBtn) {
            deptClearBtn.addEventListener('click', function() {
                deptInput.value = '';
                deptHiddenId.value = '';
                deptForm.submit();
            });
        }

        deptItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                deptItems.forEach(i => i.style.background = '');
                this.style.background = '#f1f5f9';
            });

            item.addEventListener('click', function() {
                selectDept(this);
            });
        });

        function selectDept(item) {
            deptHiddenId.value = item.dataset.id;
            deptInput.value = item.dataset.id === '' ? '' : item.dataset.name;
            deptDropdown.style.display = 'none';
            deptForm.submit();
        }

        // Keyboard navigation
        deptInput.addEventListener('keydown', function(e) {
            const visibleItems = Array.from(deptItems).filter(i => i.style.display !== 'none');
            if (visibleItems.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                deptDropdown.style.display = 'block';
                deptHighlighted = (deptHighlighted + 1) % visibleItems.length;
                updateDeptHighlight(visibleItems);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                deptDropdown.style.display = 'block';
                deptHighlighted = (deptHighlighted - 1 + visibleItems.length) % visibleItems.length;
                updateDeptHighlight(visibleItems);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (deptHighlighted >= 0 && deptHighlighted < visibleItems.length) {
                    selectDept(visibleItems[deptHighlighted]);
                } else if (visibleItems.length === 1) {
                    selectDept(visibleItems[0]);
                } else {
                    const query = deptInput.value.toLowerCase().trim();
                    const exact = visibleItems.find(i => i.dataset.name.toLowerCase() === query);
                    if (exact) {
                        selectDept(exact);
                    } else if (visibleItems.length > 0) {
                        selectDept(visibleItems[0]);
                    }
                }
            } else if (e.key === 'Escape') {
                deptDropdown.style.display = 'none';
            }
        });

        function updateDeptHighlight(visibleItems) {
            visibleItems.forEach((item, index) => {
                if (index === deptHighlighted) {
                    item.style.background = '#e2e8f0';
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.style.background = '';
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#departmentSearchForm')) {
                deptDropdown.style.display = 'none';
            }
        });
    }
});
</script>
@endpush
@endsection
