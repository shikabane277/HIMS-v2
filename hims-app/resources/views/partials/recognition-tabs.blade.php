{{--
    The Recognition module's tab strip.
    Provides navigation for Wall, Received, Sent, Certificates, and Private recognition.
--}}
@php
    $currentView = $view ?? request()->query('view', 'feed');
    $isCertificates = request()->routeIs('recognition.certificates.*') || request()->routeIs('learning.certificates.*');
    $canModerate = $canModerate ?? (auth()->check() && (auth()->user()->role === 'admin' || auth()->user()->role === 'hr_manager'));
@endphp

<nav class="hims-tabs hims-tabs-desktop" aria-label="Recognition views">
    <a href="{{ route('recognition.index') }}"
       class="hims-tab {{ !$isCertificates && $currentView === 'feed' ? 'active' : '' }}">
        <i class="bi bi-activity"></i> Wall
    </a>
    <a href="{{ route('recognition.index', ['view' => 'received']) }}"
       class="hims-tab {{ !$isCertificates && $currentView === 'received' ? 'active' : '' }}">
        <i class="bi bi-inbox"></i> Received
    </a>
    <a href="{{ route('recognition.index', ['view' => 'sent']) }}"
       class="hims-tab {{ !$isCertificates && $currentView === 'sent' ? 'active' : '' }}">
        <i class="bi bi-send"></i> Sent
    </a>
    <a href="{{ route('recognition.certificates.index') }}"
       class="hims-tab {{ $isCertificates ? 'active' : '' }}">
        <i class="bi bi-patch-check"></i> Certificates
    </a>
    @if($canModerate)
    <a href="{{ route('recognition.index', ['view' => 'private']) }}"
       class="hims-tab {{ !$isCertificates && $currentView === 'private' ? 'active' : '' }}">
        <i class="bi bi-lock"></i> Private
    </a>
    @endif
</nav>

{{-- Mobile view tab selector --}}
<div class="hims-tabs-mobile" aria-label="Recognition section dropdown">
    <div class="hims-tabs-mobile-card">
        <label for="recognitionMobileTabSelect" class="hims-tabs-mobile-label">
            <i class="bi bi-compass"></i> Section
        </label>
        <div class="hims-tabs-mobile-select-wrap">
            <select id="recognitionMobileTabSelect"
                    class="hims-input hims-select"
                    onchange="if(this.value) window.location.href=this.value"
                    aria-label="Select Recognition Section">
                <option value="{{ route('recognition.index') }}"
                        data-icon="bi bi-activity"
                        {{ !$isCertificates && $currentView === 'feed' ? 'selected' : '' }}>
                    Wall
                </option>
                <option value="{{ route('recognition.index', ['view' => 'received']) }}"
                        data-icon="bi bi-inbox"
                        {{ !$isCertificates && $currentView === 'received' ? 'selected' : '' }}>
                    Received
                </option>
                <option value="{{ route('recognition.index', ['view' => 'sent']) }}"
                        data-icon="bi bi-send"
                        {{ !$isCertificates && $currentView === 'sent' ? 'selected' : '' }}>
                    Sent
                </option>
                <option value="{{ route('recognition.certificates.index') }}"
                        data-icon="bi bi-patch-check"
                        {{ $isCertificates ? 'selected' : '' }}>
                    Certificates
                </option>
                @if($canModerate)
                <option value="{{ route('recognition.index', ['view' => 'private']) }}"
                        data-icon="bi bi-lock"
                        {{ !$isCertificates && $currentView === 'private' ? 'selected' : '' }}>
                    Private
                </option>
                @endif
            </select>
        </div>
    </div>
</div>
