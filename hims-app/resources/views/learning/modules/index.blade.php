@extends('layouts.hims')
@section('title', 'My Courses & Modules — HIMS')
@section('page-title', 'Learning Management')
@section('breadcrumb', 'HIMS / Learning / Modules')

@section('content')
@include('partials.learning-tabs')

{{-- ── HIMS COURSES CONTAINER (Light Hospital Theme with System Palette) ── --}}
<div class="lms-courses-canvas">
    {{-- Header --}}
    <div class="lms-header-section d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="lms-main-title">My courses</h1>
            <div class="lms-sub-title">COURSE OVERVIEW &middot; CLINICAL & PROFESSIONAL MODULES</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="hims-badge green"><i class="bi bi-mortarboard-fill"></i> {{ $stats['total_modules'] ?? count($modules) }} Modules</span>
            <span class="hims-badge blue"><i class="bi bi-clock-history"></i> {{ $stats['total_cpd'] ?? '9.0' }} Total CPD</span>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="lms-controls-row">
        {{-- Category Filter Dropdown --}}
        <div class="lms-filter-dropdown-wrap">
            <button type="button" class="lms-filter-btn" id="lmsFilterBtn" onclick="toggleFilterMenu()" aria-expanded="false">
                <i class="bi bi-funnel-fill text-success"></i>
                <span id="lmsCurrentFilterLabel">{{ ($category && $category !== 'all') ? $category : 'All Categories -' }}</span>
                <i class="bi bi-chevron-down ms-1" style="font-size: 11px;"></i>
            </button>
            <div class="lms-filter-menu" id="lmsFilterMenu" style="display: none;">
                <a href="{{ route('learning.modules.index') }}" class="lms-filter-item {{ empty($category) || $category === 'all' ? 'active' : '' }}">
                    <i class="bi bi-check2 {{ empty($category) || $category === 'all' ? '' : 'invisible' }}"></i> All Categories -
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('learning.modules.index', ['category' => $cat, 'search' => $search]) }}" 
                       class="lms-filter-item {{ $category === $cat ? 'active' : '' }}">
                        <i class="bi bi-check2 {{ $category === $cat ? '' : 'invisible' }}"></i> {{ $cat }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Search Bar --}}
        <form method="GET" action="{{ route('learning.modules.index') }}" class="lms-search-form">
            @if($category && $category !== 'all')
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
            <div class="lms-search-box">
                <i class="bi bi-search lms-search-icon"></i>
                <input type="text" 
                       name="search" 
                       class="lms-search-input" 
                       placeholder="Search course code, topic, or protocol title..." 
                       value="{{ $search }}" 
                       autocomplete="off">
                @if($search)
                    <a href="{{ route('learning.modules.index', ['category' => $category]) }}" class="lms-search-clear" title="Clear search">
                        <i class="bi bi-x-circle-fill"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Cards Grid (Portrait Cards with System Gradients & Geometric Textures) --}}
    <div class="lms-cards-grid">
        @forelse($modules as $index => $mod)
            <div class="lms-course-card lms-theme-{{ $mod['theme'] ?? 'emerald' }}" 
                 id="card-{{ $mod['id'] }}"
                 data-module-id="{{ $mod['id'] }}"
                 data-module-code="{{ $mod['module_code'] }}"
                 tabindex="0"
                 role="button"
                 onclick="openPdfViewer('{{ $mod['pdf_url'] }}', '{{ addslashes($mod['title']) }}', '{{ $mod['module_code'] }}', '{{ $mod['cpd_hours'] }} CPD', '{{ $mod['id'] }}', '{{ $mod['category'] }}')"
                 onkeydown="if(event.key==='Enter'||event.key===' ') openPdfViewer('{{ $mod['pdf_url'] }}', '{{ addslashes($mod['title']) }}', '{{ $mod['module_code'] }}', '{{ $mod['cpd_hours'] }} CPD', '{{ $mod['id'] }}', '{{ $mod['category'] }}')">
                
                {{-- Top Action / Context Icon (3-line hamburger badge) --}}
                <div class="lms-card-top-action" onclick="event.stopPropagation(); toggleCardMenu(event, '{{ $mod['id'] }}')">
                    <span class="lms-card-category-tag">{{ $mod['category'] }}</span>
                    <button type="button" class="lms-action-icon-btn" aria-label="Course options" title="Course options">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="lms-card-menu" id="menu-{{ $mod['id'] }}" style="display: none;">
                        <a href="javascript:void(0)" onclick="openPdfViewer('{{ $mod['pdf_url'] }}', '{{ addslashes($mod['title']) }}', '{{ $mod['module_code'] }}', '{{ $mod['cpd_hours'] }} CPD', '{{ $mod['id'] }}', '{{ $mod['category'] }}')">
                            <i class="bi bi-book-half text-success"></i> Open Module Reader
                        </a>
                        <a href="{{ $mod['pdf_url'] }}" download="HIMS-{{ $mod['module_code'] }}.pdf" onclick="event.stopPropagation();">
                            <i class="bi bi-file-earmark-arrow-down text-primary"></i> Download PDF
                        </a>
                    </div>
                </div>

                {{-- Middle Title & Subtitle --}}
                <div class="lms-card-main-content">
                    <div class="lms-card-code">{{ $mod['module_code'] }}</div>
                    <h3 class="lms-card-title">{{ $mod['title'] }}</h3>
                    <p class="lms-card-subtitle">{{ $mod['subtitle'] ?? 'Hospital Clinical Training Unit' }}</p>
                </div>

                {{-- Bottom Progress & Meter --}}
                <div class="lms-card-bottom-content">
                    <div class="lms-progress-meta">
                        <i class="bi bi-graph-up-arrow lms-trend-icon"></i>
                        <span class="lms-progress-text" id="prog-text-{{ $mod['id'] }}">{{ $mod['progress'] ?? 0 }}% complete</span>
                        <span class="ms-auto lms-cpd-badge"><i class="bi bi-award"></i> {{ $mod['cpd_hours'] }} CPD</span>
                    </div>
                    <div class="lms-progress-track">
                        <div class="lms-progress-fill" id="prog-fill-{{ $mod['id'] }}" style="width: {{ $mod['progress'] ?? 0 }}%;"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="lms-empty-state">
                <i class="bi bi-search" style="font-size: 38px; color: #94a3b8;"></i>
                <h4 style="color: #1e293b; margin-top: 14px; font-size: 16px;">No courses found</h4>
                <p style="color: #64748b; font-size: 13px;">No courses match "{{ $search }}".</p>
                <a href="{{ route('learning.modules.index') }}" class="btn-hims btn-hims-outline btn-sm">Reset All Filters</a>
            </div>
        @endforelse
    </div>
</div>

{{-- ── IN-PAGE Z-INDEXED PDF & PROTOCOL VIEWER (Scroll down to bottom to complete) ── --}}
<div id="pdfModuleViewerOverlay" class="pdf-viewer-overlay" role="dialog" aria-modal="true" aria-labelledby="pdfViewerTitle" style="display: none;">
    <div class="pdf-viewer-backdrop" onclick="closePdfViewer()"></div>
    <div class="pdf-viewer-window">
        {{-- Header bar --}}
        <div class="pdf-viewer-header">
            <div class="pdf-viewer-info">
                <div class="pdf-viewer-icon">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </div>
                <div style="min-width: 0;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="pdfViewerCode" class="pdf-viewer-badge">MOD-INF-101</span>
                        <span id="pdfViewerCpd" class="pdf-viewer-cpd">1.5 CPD</span>
                        <span id="pdfViewerCat" class="hims-badge gray" style="font-size: 11px;">Clinical Safety</span>
                        <span id="scrollProgressBadge" class="hims-badge yellow" style="font-size: 11px;">
                            <i class="bi bi-eye"></i> Reading: <span id="scrollPct">0%</span>
                        </span>
                    </div>
                    <h4 id="pdfViewerTitle" class="pdf-viewer-heading">Module Title</h4>
                </div>
            </div>
            <div class="pdf-viewer-controls">
                {{-- View Mode Toggle --}}
                <div class="btn-group btn-group-sm me-1 d-none d-md-inline-flex" role="group">
                    <button type="button" id="tabBtnDocument" class="btn btn-sm btn-light active" onclick="switchViewerTab('document')">
                        <i class="bi bi-file-text"></i> Interactive Protocol
                    </button>
                    <button type="button" id="tabBtnPdf" class="btn btn-sm btn-outline-light" onclick="switchViewerTab('pdf')">
                        <i class="bi bi-file-earmark-pdf"></i> Original PDF
                    </button>
                </div>
                <a id="pdfViewerDownloadBtn" href="#" download class="btn-hims btn-hims-outline btn-sm text-light" title="Download offline copy">
                    <i class="bi bi-download"></i> <span class="d-none d-sm-inline">Download</span>
                </a>
                <button type="button" class="btn-hims btn-hims-outline btn-sm text-light" onclick="toggleViewerFullscreen()" title="Toggle full window">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
                <button type="button" class="btn-hims btn-hims-danger btn-sm" onclick="closePdfViewer()" title="Close viewer (Esc)">
                    <i class="bi bi-x-lg"></i> <span class="d-none d-sm-inline">Close</span>
                </button>
            </div>
        </div>

        {{-- Frame / Reader Container --}}
        <div class="pdf-viewer-body" id="pdfViewerBodyWrapper">
            {{-- TAB 1: Scrollable Interactive Clinical Protocol Reader (Trackable Scroll to Bottom) --}}
            <div id="moduleReaderScrollArea" class="module-reader-scroll-area">
                <div class="module-document-wrapper">
                    {{-- Official Hospital Header --}}
                    <div class="module-doc-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #052e16; color: #4ade80; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                                    <i class="bi bi-hospital"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 15px; letter-spacing: 0.02em;">HOSPITAL INFORMATION MANAGEMENT SYSTEM (HIMS)</div>
                                    <div class="text-muted" style="font-size: 12px;">Continuing Professional Development & Clinical Safety Directorate &middot; Document Ref: <span id="docRefCode">MOD-INF-101</span></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 11px;">Accredited Clinical Module</span>
                                <div class="text-muted" style="font-size: 11px; margin-top: 3px;">Mandatory Annual Competency Refresher</div>
                            </div>
                        </div>

                        <h2 class="module-doc-title" id="docMainTitle">Hospital Infection Control & Hand Hygiene Protocols</h2>
                        <div class="module-doc-meta-strip">
                            <span><i class="bi bi-check2-circle text-success"></i> Standard Hospital Protocol</span>
                            <span><i class="bi bi-clock-history text-primary"></i> Est. Reading Time: 8-12 minutes</span>
                            <span><i class="bi bi-award text-warning"></i> 1.5 CPD Units Earned upon Completion</span>
                            <span><i class="bi bi-shield-lock text-info"></i> WHO Guidelines / DOH Compliant</span>
                        </div>
                    </div>

                    {{-- Section 1: Clinical Objectives --}}
                    <div class="module-doc-section">
                        <h4 class="module-section-title"><i class="bi bi-bullseye text-success"></i> 1. Learning Objectives & Clinical Rationale</h4>
                        <p class="module-doc-text">Healthcare-associated infections (HAIs) represent a critical determinant of patient safety and clinical outcome metrics across all hospital units. Adherence to strict hand hygiene protocols prevents the transmission of multi-drug resistant organisms (MDROs) and reduces post-operative complications by up to 45%.</p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-2 border bg-light h-100">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 13px;"><i class="bi bi-check-lg text-success"></i> Clinical Competencies</div>
                                    <ul class="mb-0 ps-3 small text-muted">
                                        <li>Identify the WHO 5 Moments of Hand Hygiene during clinical care.</li>
                                        <li>Differentiate indications for alcohol-based rub vs. soap and water.</li>
                                        <li>Perform proper surgical scrub and antiseptic handwash sequences.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-2 border bg-light h-100">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 13px;"><i class="bi bi-shield-exclamation text-danger"></i> High-Risk Clinical Areas</div>
                                    <ul class="mb-0 ps-3 small text-muted">
                                        <li>Intensive Care Units (ICU, NICU, PICU) & Isolation Wards.</li>
                                        <li>Operating Theaters & Surgical Hand-Off Suites.</li>
                                        <li>Oncology, Hemodialysis, and Immunocompromised Care units.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 2: WHO 5 Moments Protocol --}}
                    <div class="module-doc-section">
                        <h4 class="module-section-title"><i class="bi bi-hand-index-thumb text-success"></i> 2. The WHO 5 Moments of Hand Hygiene Protocol</h4>
                        <p class="module-doc-text">In accordance with Department of Health and hospital infection prevention unit standards, all healthcare personnel must execute hand hygiene during the following critical transition points:</p>
                        
                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="p-2 px-3 rounded-2 border-start border-4 border-success bg-white shadow-sm d-flex align-items-center gap-3">
                                <span class="badge bg-success rounded-pill" style="font-size: 12px; min-width: 28px;">1</span>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 13.5px;">Before Touching a Patient</strong>
                                    <span class="text-muted small">Clean hands before touching a patient when approaching him/her. Protects patient against harmful germs carried on your hands.</span>
                                </div>
                            </div>
                            <div class="p-2 px-3 rounded-2 border-start border-4 border-primary bg-white shadow-sm d-flex align-items-center gap-3">
                                <span class="badge bg-primary rounded-pill" style="font-size: 12px; min-width: 28px;">2</span>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 13.5px;">Before Clean / Aseptic Procedures</strong>
                                    <span class="text-muted small">Clean hands immediately before any aseptic task (wound care, IV insertion, catheterization). Protects patient from harmful germs entering their body.</span>
                                </div>
                            </div>
                            <div class="p-2 px-3 rounded-2 border-start border-4 border-danger bg-white shadow-sm d-flex align-items-center gap-3">
                                <span class="badge bg-danger rounded-pill" style="font-size: 12px; min-width: 28px;">3</span>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 13.5px;">After Body Fluid Exposure Risk</strong>
                                    <span class="text-muted small">Clean hands immediately after exposure to body fluids (blood, secretions, mucous) and immediately after glove removal.</span>
                                </div>
                            </div>
                            <div class="p-2 px-3 rounded-2 border-start border-4 border-warning bg-white shadow-sm d-flex align-items-center gap-3">
                                <span class="badge bg-warning text-dark rounded-pill" style="font-size: 12px; min-width: 28px;">4</span>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 13.5px;">After Touching a Patient</strong>
                                    <span class="text-muted small">Clean hands when leaving the patient's side after touching them. Protects yourself and the healthcare environment from harmful germs.</span>
                                </div>
                            </div>
                            <div class="p-2 px-3 rounded-2 border-start border-4 border-secondary bg-white shadow-sm d-flex align-items-center gap-3">
                                <span class="badge bg-secondary rounded-pill" style="font-size: 12px; min-width: 28px;">5</span>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 13.5px;">After Touching Patient Surroundings</strong>
                                    <span class="text-muted small">Clean hands after touching any object or furniture in the patient's immediate surroundings when leaving, even without direct patient contact.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 3: Proper Technique & Duration Matrix --}}
                    <div class="module-doc-section">
                        <h4 class="module-section-title"><i class="bi bi-droplet-half text-success"></i> 3. Decontamination Technique & Exposure Matrix</h4>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm align-middle" style="font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Method</th>
                                        <th>Recommended Product</th>
                                        <th>Duration</th>
                                        <th>Mandatory Clinical Indications</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Alcohol-Based Hand Rub (ABHR)</strong></td>
                                        <td>70% Isopropyl or Ethyl Alcohol Gel/Solution</td>
                                        <td><span class="badge bg-info-subtle text-info border">20 - 30 seconds</span></td>
                                        <td>Routine decontamination when hands are <em>not</em> visibly soiled. Preferred method for general bedside rounds.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Antiseptic Hand Wash</strong></td>
                                        <td>Plain soap + water or Chlorhexidine Gluconate 4%</td>
                                        <td><span class="badge bg-warning-subtle text-warning border">40 - 60 seconds</span></td>
                                        <td>When hands are visibly soiled with blood/fluids, after caring for patients with confirmed <em>C. difficile</em>, or post-toilet.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Surgical Scrub</strong></td>
                                        <td>Povidone-Iodine 7.5% or Chlorhexidine 4%</td>
                                        <td><span class="badge bg-danger-subtle text-danger border">3 - 5 minutes</span></td>
                                        <td>Pre-operative decontamination before all sterile surgical procedures. Hands elevated above elbows.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Section 4: Personal Protective Equipment (PPE) & Cross-Contamination --}}
                    <div class="module-doc-section">
                        <h4 class="module-section-title"><i class="bi bi-shield-check text-success"></i> 4. PPE Donning, Doffing & Environmental Safety</h4>
                        <p class="module-doc-text">Gloves do NOT substitute for hand hygiene. Hand hygiene must be performed before putting on gloves and immediately after taking them off. Contamination frequently occurs during improper doffing sequences:</p>
                        
                        <div class="p-3 bg-light rounded-2 border mb-3">
                            <div class="fw-bold text-dark mb-2" style="font-size: 13.5px;"><i class="bi bi-arrow-repeat text-primary"></i> Mandatory Doffing Sequence (Highest Risk of Self-Contamination)</div>
                            <ol class="small text-muted mb-0 ps-3">
                                <li><strong>Gloves:</strong> Grasp outside edge near wrist, peel away inside out; hold in opposite hand and peel second glove over first.</li>
                                <li><strong>Gown:</strong> Unfasten ties, peel away from neck and shoulders, touching inside of gown only; turn inside out and bundle into disposal bin.</li>
                                <li><strong>Hand Hygiene:</strong> Execute alcohol hand rub immediately prior to touching face shield or mask.</li>
                                <li><strong>Face Shield / Goggles:</strong> Handle by head strap only without touching front visor.</li>
                                <li><strong>Respirator / Mask:</strong> Grasp bottom ties/elastics, then top ties, remove without touching front filter.</li>
                                <li><strong>Final Hand Hygiene:</strong> Thorough hand wash with soap and water or alcohol rub.</li>
                            </ol>
                        </div>
                    </div>

                    {{-- Section 5: Clinical Policy Attestation & Completion Stamp --}}
                    <div class="module-doc-section">
                        <h4 class="module-section-title"><i class="bi bi-pen text-success"></i> 5. Professional Attestation & Hospital Code Compliance</h4>
                        <p class="module-doc-text">By completing this module, you acknowledge that you have reviewed, understood, and agreed to maintain compliance with all hospital infection control guidelines, patient safety mandates, and clinical audit benchmarks.</p>
                        
                        {{-- Final Milestone Marker (Scroll Target for Reader) --}}
                        <div id="moduleCompletionCheckpoint" class="module-completion-box">
                            <div class="completion-icon-wrapper">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">End of Module Document Reached</h4>
                            <p class="text-muted small mb-3" style="max-width: 520px; margin: 0 auto;">
                                You have successfully read through the entire curriculum. The verification requirement is fulfilled. Click the <strong>"Mark as Read (100%)"</strong> button below to log your completion and credit CPD hours.
                            </p>
                            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 bg-white border rounded-pill shadow-sm small text-success fw-semibold">
                                <i class="bi bi-unlock-fill"></i> Completion Action Unlocked
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 2: Embedded PDF Object (Direct In-Page PDF Frame) --}}
            <iframe id="pdfViewerIframe" 
                    src="about:blank" 
                    title="HIMS PDF Module Reader" 
                    class="pdf-viewer-frame"
                    style="display: none;">
            </iframe>
        </div>

        {{-- Footer bar with Scroll Lock Indicator & Mark Complete Button --}}
        <div class="pdf-viewer-footer">
            <div id="scrollHintNotice" class="d-flex align-items-center gap-2 text-muted" style="font-size: 12.5px;">
                <i class="bi bi-arrow-down-circle text-primary"></i>
                <span>Please scroll down to the <strong>very bottom</strong> of the module to verify and mark complete (<span id="readingProgressNumber">0%</span> read).</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" 
                        id="btnMarkComplete" 
                        class="btn-hims btn-secondary disabled" 
                        disabled 
                        onclick="markModuleComplete()"
                        title="You must scroll down to the very bottom to complete this module">
                    <i class="bi bi-lock-fill"></i> Scroll to Bottom to Complete
                </button>
                <button type="button" class="btn-hims btn-hims-outline btn-sm" onclick="closePdfViewer()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── HIMS HOSPITAL SYSTEM THEME FOR MODULES CANVAS ── */
.lms-courses-canvas {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 28px 32px 42px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    color: #0f172a;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.lms-header-section {
    margin-bottom: 22px;
}

.lms-main-title {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    letter-spacing: -0.02em;
}

.lms-sub-title {
    font-size: 11px;
    font-weight: 700;
    color: #15803d; /* HIMS Primary Emerald */
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* ── CONTROLS ROW (Clean Hospital Look) ── */
.lms-controls-row {
    margin-bottom: 26px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.lms-filter-dropdown-wrap {
    position: relative;
    display: inline-block;
}

.lms-filter-btn {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.lms-filter-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.lms-filter-menu {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 6px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    min-width: 220px;
    z-index: 50;
    overflow: hidden;
}

.lms-filter-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    color: #334155;
    font-size: 13px;
    text-decoration: none;
    transition: background 0.12s ease;
}

.lms-filter-item:hover, .lms-filter-item.active {
    background: #f0fdf4;
    color: #15803d;
    font-weight: 600;
}

.lms-search-form {
    width: 100%;
}

.lms-search-box {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
}

.lms-search-icon {
    position: absolute;
    left: 16px;
    color: #94a3b8;
    font-size: 15px;
    pointer-events: none;
}

.lms-search-input {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 11px 16px 11px 42px;
    color: #0f172a;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
}

.lms-search-input:focus {
    border-color: #16a34a;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

.lms-search-input::placeholder {
    color: #94a3b8;
}

.lms-search-clear {
    position: absolute;
    right: 14px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 15px;
}

.lms-search-clear:hover {
    color: #475569;
}

/* ── PORTRAIT COURSE CARDS (Hospital Gradient Systems) ── */
.lms-cards-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}

@media (max-width: 1300px) {
    .lms-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 700px) {
    .lms-cards-grid {
        grid-template-columns: 1fr;
    }
    .lms-courses-canvas {
        padding: 20px 16px 30px;
    }
}

.lms-course-card {
    height: 380px;
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 22px 20px 20px;
    cursor: pointer;
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    user-select: none;
    outline: none;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.lms-course-card:hover, .lms-course-card:focus-visible {
    transform: translateY(-5px);
    box-shadow: 0 16px 28px -4px rgba(15, 23, 42, 0.28), 0 0 0 2px rgba(22, 163, 74, 0.4);
}

/* 1. EMERALD THEME (Primary Hospital Green Gradient) */
.lms-theme-emerald, .lms-theme-teal {
    background: linear-gradient(150deg, #052e16 0%, #14532d 55%, #15803d 100%) !important;
    box-shadow: 0 8px 20px -3px rgba(5, 46, 22, 0.35);
}

/* 2. CLINICAL SLATE THEME (Hospital Slate & Silver) */
.lms-theme-slate {
    background: linear-gradient(150deg, #0f172a 0%, #1e293b 60%, #334155 100%) !important;
    box-shadow: 0 8px 20px -3px rgba(15, 23, 42, 0.35);
}

/* 3. WARM BRONZE THEME */
.lms-theme-amber {
    background: linear-gradient(150deg, #451a03 0%, #78350f 60%, #b45309 100%) !important;
    box-shadow: 0 8px 20px -3px rgba(69, 26, 3, 0.35);
}

/* 4. HOSPITAL BURGUNDY THEME */
.lms-theme-crimson {
    background: linear-gradient(150deg, #4c0519 0%, #831843 60%, #9f1239 100%) !important;
    box-shadow: 0 8px 20px -3px rgba(76, 5, 25, 0.35);
}

/* ── CARD INTERNALS ── */
.lms-card-top-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.lms-card-category-tag {
    background: rgba(0, 0, 0, 0.3);
    color: rgba(255, 255, 255, 0.9);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.02em;
    backdrop-filter: blur(4px);
}

.lms-action-icon-btn {
    background: rgba(0, 0, 0, 0.32);
    border: none;
    border-radius: 6px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.95);
    font-size: 18px;
    cursor: pointer;
    transition: background 0.15s ease;
}

.lms-action-icon-btn:hover {
    background: rgba(0, 0, 0, 0.6);
    color: #ffffff;
}

.lms-card-menu {
    position: absolute;
    top: 38px;
    right: 0;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    min-width: 180px;
    z-index: 20;
    overflow: hidden;
}

.lms-card-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    color: #1e293b;
    font-size: 12.5px;
    text-decoration: none;
    transition: background 0.12s ease;
}

.lms-card-menu a:hover {
    background: #f8fafc;
    color: #0f172a;
}

.lms-card-main-content {
    margin-top: 10px;
    margin-bottom: auto;
}

.lms-card-code {
    font-size: 11.5px;
    font-weight: 700;
    color: #86efac; /* bright mint green */
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    text-transform: uppercase;
}

.lms-card-title {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.25;
    color: #ffffff;
    margin: 0 0 8px 0;
    letter-spacing: -0.01em;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
}

.lms-card-subtitle {
    font-size: 12.5px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    line-height: 1.45;
}

.lms-card-bottom-content {
    margin-top: 26px;
}

.lms-progress-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 8px;
}

.lms-trend-icon {
    font-size: 14px;
    color: #4ade80; /* bright green */
}

.lms-cpd-badge {
    background: rgba(0, 0, 0, 0.3);
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #fde047; /* bright yellow */
}

.lms-progress-track {
    width: 100%;
    height: 7px;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 4px;
    overflow: hidden;
}

.lms-progress-fill {
    height: 100%;
    background: #22c55e; /* Vibrant hospital green */
    border-radius: 4px;
    transition: width 0.4s ease;
}

.lms-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 48px 20px;
}

/* ── HIGH Z-INDEXED IN-PAGE MODAL VIEWER ── */
.pdf-viewer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    z-index: 999999 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.pdf-viewer-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1;
}

.pdf-viewer-window {
    position: relative;
    z-index: 2;
    width: 96%;
    max-width: 1200px;
    height: 94vh;
    background: #ffffff;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.1);
    overflow: hidden;
    animation: pdfViewerPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes pdfViewerPop {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.pdf-viewer-header {
    background: #052e16; /* HIMS Deep Forest Green */
    color: #f8fafc;
    padding: 14px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid #14532d;
}

.pdf-viewer-info {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.pdf-viewer-icon {
    width: 42px;
    height: 42px;
    background: #15803d;
    color: #ffffff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.pdf-viewer-heading {
    font-size: 15.5px;
    font-weight: 700;
    color: #ffffff;
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pdf-viewer-badge {
    background: rgba(255, 255, 255, 0.18);
    color: #86efac;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
}

.pdf-viewer-cpd {
    background: #d97706;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
}

.pdf-viewer-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.pdf-viewer-body {
    flex-grow: 1;
    background: #f1f5f9;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── SCROLLABLE PROTOCOL READER CONTAINER ── */
.module-reader-scroll-area {
    width: 100%;
    height: 100%;
    overflow-y: auto;
    padding: 30px 24px;
    box-sizing: border-box;
    scroll-behavior: smooth;
}

.module-document-wrapper {
    max-width: 860px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 40px 48px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

@media (max-width: 768px) {
    .module-document-wrapper {
        padding: 24px 18px;
    }
}

.module-doc-header {
    margin-bottom: 30px;
}

.module-doc-title {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 14px 0 10px;
    line-height: 1.3;
}

.module-doc-meta-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 12.5px;
    color: #64748b;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.module-doc-section {
    margin-bottom: 34px;
}

.module-section-title {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.module-doc-text {
    font-size: 14px;
    line-height: 1.7;
    color: #334155;
    margin-bottom: 16px;
}

.module-completion-box {
    margin-top: 36px;
    padding: 32px 24px;
    background: #f0fdf4;
    border: 2px dashed #16a34a;
    border-radius: 12px;
    text-align: center;
}

.completion-icon-wrapper {
    font-size: 42px;
    margin-bottom: 10px;
}

/* Original PDF Iframe */
.pdf-viewer-frame {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    background: #525659;
}

/* Footer */
.pdf-viewer-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.btn-hims-danger {
    background: #ef4444;
    color: #ffffff !important;
    border: 1px solid #dc2626;
}
.btn-hims-danger:hover {
    background: #dc2626;
    color: #ffffff !important;
}

.btn-hims-success {
    background: #16a34a;
    color: #ffffff !important;
    border: 1px solid #15803d;
}
.btn-hims-success:hover {
    background: #15803d;
    color: #ffffff !important;
}

.pdf-viewer-window.is-fullscreen {
    width: 100vw;
    height: 100vh;
    max-width: none;
    border-radius: 0;
}
</style>

<script>
let currentActiveModuleCode = null;
let currentActiveModuleId = null;
let hasScrolledToBottom = false;

function toggleFilterMenu() {
    const menu = document.getElementById('lmsFilterMenu');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
}

function toggleCardMenu(event, id) {
    event.stopPropagation();
    // Close other open menus
    document.querySelectorAll('.lms-card-menu').forEach(m => {
        if (m.id !== 'menu-' + id) m.style.display = 'none';
    });
    const m = document.getElementById('menu-' + id);
    if (m) {
        m.style.display = m.style.display === 'none' ? 'block' : 'none';
    }
}

// Close menus on outside click
document.addEventListener('click', function() {
    const fMenu = document.getElementById('lmsFilterMenu');
    if (fMenu) fMenu.style.display = 'none';
    document.querySelectorAll('.lms-card-menu').forEach(m => m.style.display = 'none');
});

function switchViewerTab(tab) {
    const docArea = document.getElementById('moduleReaderScrollArea');
    const iframe = document.getElementById('pdfViewerIframe');
    const tabDoc = document.getElementById('tabBtnDocument');
    const tabPdf = document.getElementById('tabBtnPdf');

    if (tab === 'pdf') {
        if (docArea) docArea.style.display = 'none';
        if (iframe) iframe.style.display = 'block';
        if (tabDoc) { tabDoc.classList.remove('btn-light', 'active'); tabDoc.classList.add('btn-outline-light'); }
        if (tabPdf) { tabPdf.classList.add('btn-light', 'active'); tabPdf.classList.remove('btn-outline-light'); }
    } else {
        if (docArea) docArea.style.display = 'block';
        if (iframe) iframe.style.display = 'none';
        if (tabDoc) { tabDoc.classList.add('btn-light', 'active'); tabDoc.classList.remove('btn-outline-light'); }
        if (tabPdf) { tabPdf.classList.remove('btn-light', 'active'); tabPdf.classList.add('btn-outline-light'); }
    }
}

function openPdfViewer(url, title, code, cpd, id, category) {
    const overlay = document.getElementById('pdfModuleViewerOverlay');
    const iframe = document.getElementById('pdfViewerIframe');
    const titleEl = document.getElementById('pdfViewerTitle');
    const codeEl = document.getElementById('pdfViewerCode');
    const cpdEl = document.getElementById('pdfViewerCpd');
    const catEl = document.getElementById('pdfViewerCat');
    const docMainTitle = document.getElementById('docMainTitle');
    const docRefCode = document.getElementById('docRefCode');
    const downloadBtn = document.getElementById('pdfViewerDownloadBtn');
    const scrollArea = document.getElementById('moduleReaderScrollArea');
    const btnMarkComplete = document.getElementById('btnMarkComplete');
    const hint = document.getElementById('scrollHintNotice');
    const scrollPct = document.getElementById('scrollPct');
    const readingProgressNumber = document.getElementById('readingProgressNumber');
    const badge = document.getElementById('scrollProgressBadge');

    if (!overlay || !iframe) return;

    currentActiveModuleCode = code;
    currentActiveModuleId = id;

    // Attach directly to body so it overlays entire browser window over sidebars and topbars
    if (overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }

    if (titleEl) titleEl.textContent = title;
    if (docMainTitle) docMainTitle.textContent = title;
    if (codeEl) codeEl.textContent = code;
    if (docRefCode) docRefCode.textContent = code;
    if (cpdEl) cpdEl.textContent = cpd;
    if (catEl) catEl.textContent = category || 'Clinical Safety';
    if (downloadBtn) {
        downloadBtn.href = url;
        downloadBtn.setAttribute('download', 'HIMS-' + code + '.pdf');
    }

    // Default to document tab
    switchViewerTab('document');

    // Load PDF inside the in-page frame
    iframe.src = url + '#toolbar=1&navpanes=0&view=FitH';

    // Check if already completed previously
    const isAlreadyCompleted = localStorage.getItem('hims_module_' + id) === '100';

    if (isAlreadyCompleted) {
        hasScrolledToBottom = true;
        if (btnMarkComplete) {
            btnMarkComplete.disabled = false;
            btnMarkComplete.className = 'btn-hims btn-hims-success btn-sm';
            btnMarkComplete.innerHTML = '<i class="bi bi-check2-circle"></i> Completed (100%)';
        }
        if (hint) {
            hint.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Completed: You have reviewed this clinical module.</span>';
        }
        if (badge) {
            badge.className = 'hims-badge green';
            badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> 100% Complete';
        }
    } else {
        // Reset scroll state
        hasScrolledToBottom = false;
        if (scrollArea) scrollArea.scrollTop = 0;
        if (scrollPct) scrollPct.textContent = '0%';
        if (readingProgressNumber) readingProgressNumber.textContent = '0%';
        if (badge) {
            badge.className = 'hims-badge yellow';
            badge.innerHTML = '<i class="bi bi-eye"></i> Reading: <span id="scrollPct">0%</span>';
        }
        if (btnMarkComplete) {
            btnMarkComplete.disabled = true;
            btnMarkComplete.className = 'btn-hims btn-secondary disabled';
            btnMarkComplete.innerHTML = '<i class="bi bi-lock-fill"></i> Scroll to Bottom to Complete';
        }
        if (hint) {
            hint.innerHTML = '<i class="bi bi-arrow-down-circle text-primary"></i> <span>Please scroll down to the <strong>very bottom</strong> of the module to verify and mark complete (<span id="readingProgressNumber">0%</span> read).</span>';
        }
    }

    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Scroll Listener for Bottom Detection
document.addEventListener('DOMContentLoaded', function() {
    const scrollArea = document.getElementById('moduleReaderScrollArea');
    if (!scrollArea) return;

    scrollArea.addEventListener('scroll', function() {
        if (hasScrolledToBottom) return;

        const distance = scrollArea.scrollHeight - scrollArea.scrollTop - scrollArea.clientHeight;
        const maxScroll = scrollArea.scrollHeight - scrollArea.clientHeight;
        const percent = maxScroll > 0 ? Math.min(100, Math.max(0, Math.round((scrollArea.scrollTop / maxScroll) * 100))) : 100;

        const pctEl = document.getElementById('scrollPct');
        const numEl = document.getElementById('readingProgressNumber');
        if (pctEl) pctEl.textContent = percent + '%';
        if (numEl) numEl.textContent = percent + '%';

        // When user reaches within 45px of the very bottom: unlock!
        if (distance <= 45) {
            hasScrolledToBottom = true;
            const btn = document.getElementById('btnMarkComplete');
            const hint = document.getElementById('scrollHintNotice');
            const badge = document.getElementById('scrollProgressBadge');

            if (btn) {
                btn.disabled = false;
                btn.className = 'btn-hims btn-hims-success btn-sm';
                btn.innerHTML = '<i class="bi bi-check2-circle"></i> Mark as Read (100%)';
            }
            if (hint) {
                hint.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Bottom reached! You may now mark this module as complete.</span>';
            }
            if (badge) {
                badge.className = 'hims-badge green';
                badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> 100% Read';
            }
        }
    });

    // Check stored completed states on load
    document.querySelectorAll('.lms-course-card').forEach(card => {
        const id = card.dataset.moduleId;
        if (id && localStorage.getItem('hims_module_' + id) === '100') {
            const progText = document.getElementById('prog-text-' + id);
            const progFill = document.getElementById('prog-fill-' + id);
            if (progText) progText.textContent = '100% complete';
            if (progFill) progFill.style.width = '100%';
        }
    });
});

function closePdfViewer() {
    const overlay = document.getElementById('pdfModuleViewerOverlay');
    const iframe = document.getElementById('pdfViewerIframe');
    if (!overlay) return;

    overlay.style.display = 'none';
    if (iframe) iframe.src = 'about:blank';
    document.body.style.overflow = '';
}

function markModuleComplete() {
    if (!currentActiveModuleId && !currentActiveModuleCode) return;
    
    // Save completion state
    if (currentActiveModuleId) {
        localStorage.setItem('hims_module_' + currentActiveModuleId, '100');
    }

    // Update local card UI to show 100% complete
    const progText = document.getElementById('prog-text-' + currentActiveModuleId);
    const progFill = document.getElementById('prog-fill-' + currentActiveModuleId);
    if (progText) progText.textContent = '100% complete';
    if (progFill) progFill.style.width = '100%';

    // Also update any card matching the code
    document.querySelectorAll('.lms-course-card').forEach(card => {
        if (card.dataset.moduleCode === currentActiveModuleCode) {
            const pText = card.querySelector('.lms-progress-text');
            const pFill = card.querySelector('.lms-progress-fill');
            if (pText) pText.textContent = '100% complete';
            if (pFill) pFill.style.width = '100%';
        }
    });

    // Update button in viewer
    const btn = document.getElementById('btnMarkComplete');
    if (btn) {
        btn.innerHTML = '<i class="bi bi-check-all"></i> Completed & Logged!';
        btn.disabled = true;
    }

    // Close after short feedback
    setTimeout(() => {
        closePdfViewer();
    }, 700);
}

function toggleViewerFullscreen() {
    const win = document.querySelector('.pdf-viewer-window');
    if (win) {
        win.classList.toggle('is-fullscreen');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('pdfModuleViewerOverlay');
        if (overlay && overlay.style.display === 'flex') {
            closePdfViewer();
        }
    }
});
</script>
@endsection
