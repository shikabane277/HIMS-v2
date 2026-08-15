{{--
    The Learning module's tab strip.

    Learning and Compliance used to be two sidebar entries over one domain. They
    are now one module, and this strip is the seam: six tabs, each gated on the
    same Gate as the route it points at, so a member of staff is never offered a
    tab that would refuse them. Staff see three (Overview, My CPD, Pathways);
    supervisors and above see all six.

    Every tab is a real navigation — no JS, no client-side panel switching — so a
    tab is bookmarkable, survives a refresh, and keeps working with the browser's
    back button. `request()->routeIs(...)` decides the active one, and each tab
    claims its whole family so a drill-down (a roster, a rule list) keeps its
    parent tab lit rather than showing no tab at all.
--}}
<nav class="hims-tabs" aria-label="Learning sections">
    <a href="{{ route('learning.index') }}"
       class="hims-tab {{ request()->routeIs('learning.index') || request()->routeIs('learning.courses.*') ? 'active' : '' }}">
        <i class="bi bi-grid"></i> Overview
    </a>

    @can('view-compliance')
    <a href="{{ route('learning.assignments.index') }}"
       class="hims-tab {{ request()->routeIs('learning.assignments.*') ? 'active' : '' }}">
        <i class="bi bi-clipboard-check"></i> Required Training
    </a>
    <a href="{{ route('learning.renewals.index') }}"
       class="hims-tab {{ request()->routeIs('learning.renewals.*') ? 'active' : '' }}">
        <i class="bi bi-arrow-repeat"></i> Renewals
    </a>
    @endcan

    <a href="{{ route('learning.cpd.index') }}"
       class="hims-tab {{ request()->routeIs('learning.cpd.*') || request()->routeIs('learning.cycles.mine') ? 'active' : '' }}">
        <i class="bi bi-award"></i> My CPD
    </a>

    <a href="{{ route('learning.pathways.index') }}"
       class="hims-tab {{ request()->routeIs('learning.pathways.*') ? 'active' : '' }}">
        <i class="bi bi-signpost-split"></i> Pathways
    </a>

    <a href="{{ route('training.index') }}"
       class="hims-tab {{ request()->routeIs('training.index') || request()->routeIs('training.sessions.*') ? 'active' : '' }}">
        <i class="bi bi-calendar-event"></i> Sessions
    </a>

    <a href="{{ route('training.venues.index') }}"
       class="hims-tab {{ request()->routeIs('training.venues.*') ? 'active' : '' }}">
        <i class="bi bi-geo-alt"></i> Venues
    </a>

    @can('view-compliance')
    <a href="{{ route('learning.accreditation') }}"
       class="hims-tab {{ request()->routeIs('learning.accreditation') || request()->routeIs('learning.accounts') ? 'active' : '' }}">
        <i class="bi bi-file-earmark-text"></i> Reports
    </a>
    @endcan
</nav>
