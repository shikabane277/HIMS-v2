{{--
    The Training module's tab strip.
    Provides dedicated navigation for Training Sessions, Required Training, and Venues.
--}}
<nav class="hims-tabs hims-tabs-desktop" aria-label="Training sections">
    <a href="{{ route('training.index') }}"
       class="hims-tab {{ request()->routeIs('training.index') || request()->routeIs('training.sessions.*') ? 'active' : '' }}">
        <i class="bi bi-calendar-event"></i> Sessions
    </a>

    @can('view-compliance')
    <a href="{{ route('learning.assignments.index') }}"
       class="hims-tab {{ request()->routeIs('learning.assignments.*') || request()->routeIs('compliance.assignments.*') || request()->routeIs('compliance.index') ? 'active' : '' }}">
        <i class="bi bi-clipboard-check"></i> Required Training
    </a>
    @endcan

    <a href="{{ route('training.venues.index') }}"
       class="hims-tab {{ request()->routeIs('training.venues.*') ? 'active' : '' }}">
        <i class="bi bi-geo-alt"></i> Venues
    </a>
</nav>

{{-- Mobile view tab selector --}}
<div class="hims-tabs-mobile" aria-label="Training section dropdown">
    <div class="hims-tabs-mobile-card">
        <label for="trainingMobileTabSelect" class="hims-tabs-mobile-label">
            <i class="bi bi-compass"></i> Section
        </label>
        <div class="hims-tabs-mobile-select-wrap">
            <select id="trainingMobileTabSelect"
                    class="hims-input hims-select"
                    onchange="if(this.value) window.location.href=this.value"
                    aria-label="Select Training Section">
                <option value="{{ route('training.index') }}"
                        data-icon="bi bi-calendar-event"
                        {{ request()->routeIs('training.index') || request()->routeIs('training.sessions.*') ? 'selected' : '' }}>
                    Sessions
                </option>
                @can('view-compliance')
                <option value="{{ route('learning.assignments.index') }}"
                        data-icon="bi bi-clipboard-check"
                        {{ request()->routeIs('learning.assignments.*') || request()->routeIs('compliance.assignments.*') || request()->routeIs('compliance.index') ? 'selected' : '' }}>
                    Required Training
                </option>
                @endcan
                <option value="{{ route('training.venues.index') }}"
                        data-icon="bi bi-geo-alt"
                        {{ request()->routeIs('training.venues.*') ? 'selected' : '' }}>
                    Venues
                </option>
            </select>
        </div>
    </div>
</div>
