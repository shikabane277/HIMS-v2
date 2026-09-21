<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.onerror = function(message, source, lineno, colno, error) {
            fetch('{{ route("log-error") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: message, source: source, lineno: lineno })
            });
            return false;
        };
    </script>
    <title>@yield('title', 'Dashboard') — HIMS</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('partials.app-css')
    @stack('head')
</head>
<body>

<!-- SIDEBAR -->
<aside class="hims-sidebar" id="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-heart-pulse"></i></div>
        <div class="brand-text">
            <span class="brand-name">HIMS</span>
            <span class="brand-sub">Performance & Development</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Overview</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-grid-1x2"></i></span> Dashboard
        </a>
        {{-- Every role, including staff who have no directory access at all.
             The route resolves the signed-in user's own employee_id. --}}
        <a href="{{ route('employees.progression.mine') }}" class="sidebar-link {{ request()->routeIs('employees.progression*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-graph-up"></i></span> My Development
        </a>

        <div class="sidebar-section-label">HR Modules</div>
        <a href="{{ route('performance.index') }}" class="sidebar-link {{ request()->routeIs('performance.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-clipboard-data"></i></span> Performance
        </a>
        <a href="{{ route('competency.index') }}" class="sidebar-link {{ request()->routeIs('competency.index') || request()->routeIs('competency.assessments.*') || request()->routeIs('competency.credentials.*') || request()->routeIs('competency.domains.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-bullseye"></i></span> Competency
        </a>
        @can('run-gap-analysis')
        <a href="{{ route('competency.gap.index') }}" class="sidebar-link {{ request()->routeIs('competency.gap.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-cpu"></i></span> AI Gap Analysis
        </a>
        @endcan
        {{-- One entry for the whole Learning module, both halves. The
             institutional pages (what the hospital requires, who is short of it,
             the accreditation report) used to be a second "Compliance" sidebar
             entry; they are now tabs inside Learning, gated individually by
             partials/learning-tabs.blade.php. `learning.*` covers all of them. --}}
        <a href="{{ route('learning.index') }}" class="sidebar-link {{ request()->routeIs('learning.*') || request()->routeIs('training.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-book"></i></span> Learning
        </a>
        <a href="{{ route('recognition.index') }}" class="sidebar-link {{ request()->routeIs('recognition.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-stars"></i></span> Recognition
        </a>
        @can('view-succession')
        <a href="{{ route('succession.index') }}" class="sidebar-link {{ request()->routeIs('succession.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-trophy"></i></span> Succession
        </a>
        @endcan

        @canany(['view-employees','manage-departments','manage-users'])
        <div class="sidebar-section-label">Admin</div>
        @can('view-employees')
        {{-- Excludes employees.progression*, which has its own entry above. --}}
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') && ! request()->routeIs('employees.progression*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-people"></i></span> Employees
        </a>
        @endcan
        @can('manage-departments')
        <a href="{{ route('departments.index') }}" class="sidebar-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-building"></i></span> Departments
        </a>
        @endcan
        @can('manage-users')
        <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-shield-lock"></i></span> Users & Access
        </a>
        @endcan
        @endcanany
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile.edit') }}" class="sidebar-user">
            <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</div>
            <div class="user-info">
                <span class="user-name">{{ Auth::user()->name ?? 'User' }}</span>
                <span class="user-role">{{ ucwords(str_replace('_',' ', Auth::user()->role ?? 'staff')) }}</span>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <a href="#" onclick="this.closest('form').submit()" class="sidebar-link" style="margin-top:4px">
                <span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span> Logout
            </a>
        </form>
    </div>
</aside>

<!-- SIDEBAR BACKDROP (mobile) -->
<div class="hims-sidebar-backdrop" id="sidebar-backdrop"></div>

<!-- TOPBAR -->
<header class="hims-topbar">
    <div class="topbar-left">
        <button class="hims-menu-toggle" id="menu-toggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
            <p class="page-breadcrumb">@yield('breadcrumb', 'HIMS / Dashboard')</p>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-menu-wrap">
            <button class="topbar-btn" id="notif-btn" title="Notifications" aria-haspopup="true" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <span class="notif-count" id="notif-count" @if(empty($himsUnreadCount)) style="display:none" @endif>
                    {{ ($himsUnreadCount ?? 0) > 99 ? '99+' : ($himsUnreadCount ?? 0) }}
                </span>
            </button>
            <div class="topbar-dropdown notif-dropdown" id="notif-dropdown" role="menu" aria-label="Notifications">
                <div class="topbar-dropdown-header notif-dropdown-header">
                    <div>
                        <div class="notif-heading">Notifications</div>
                        <div class="notif-heading-count" id="notif-heading-count">
                            {{ ($himsUnreadCount ?? 0) > 0 ? $himsUnreadCount.' unread' : 'All caught up' }}
                        </div>
                    </div>
                    <button type="button" id="notif-clear" class="topbar-dropdown-action"
                            @if(empty($himsUnreadCount)) style="display:none" @endif>Mark all as read</button>
                </div>
                <div class="topbar-dropdown-body notif-list" id="notif-list">
                    @forelse($himsNotifications ?? [] as $note)
                        <a href="{{ $note->destination_url }}"
                           class="notif-item {{ $note->is_read ? 'read' : 'unread' }}"
                           role="menuitem"
                           data-notification-id="{{ $note->notification_id }}"
                           data-read-url="{{ route('notifications.read', $note->notification_id) }}">
                            <span class="notif-item-icon {{ $note->tone }}" aria-hidden="true">
                                <i class="bi {{ $note->icon }}"></i>
                            </span>
                            <span class="notif-item-content">
                                <span class="notif-item-title">{{ $note->title }}</span>
                                @if($note->message)
                                    <span class="notif-item-message">{{ $note->message }}</span>
                                @endif
                                <span class="notif-item-time">{{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}</span>
                            </span>
                            @unless($note->is_read)
                                <span class="notif-unread-marker" aria-label="Unread"></span>
                            @endunless
                        </a>
                    @empty
                        <div class="topbar-dropdown-empty" id="notif-empty">
                            <i class="bi bi-bell-slash"></i>
                            <span>No notifications yet.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="topbar-menu-wrap">
            <button class="topbar-btn" id="help-btn" title="Help &amp; FAQ" aria-haspopup="true" aria-expanded="false">
                <i class="bi bi-question-circle"></i>
            </button>
            <div class="topbar-dropdown" id="help-dropdown" role="menu">
                <div class="topbar-dropdown-header">
                    <span>Help &amp; FAQ</span>
                </div>
                <div class="topbar-dropdown-body">
                    <details class="help-faq">
                        <summary>How do I run an AI competency gap analysis?</summary>
                        <p>Open <strong>AI Gap Analysis</strong> from the sidebar (requires HR or admin access). Pick an organisation, department, or employee scope and the assistant compares assessed proficiency against role requirements.</p>
                    </details>
                    <details class="help-faq">
                        <summary>Why is the AI assistant unavailable?</summary>
                        <p>The chat bubble degrades gracefully when no AI provider is configured. An admin sets <code>AI_PROVIDER</code> and the matching API key in the server environment, then runs <code>php artisan config:clear</code>.</p>
                    </details>
                    <details class="help-faq">
                        <summary>How do I add a succession candidate?</summary>
                        <p>Go to <strong>Succession</strong> → open a position → <strong>Nominate candidate</strong>. Performance and potential scores (1–5) derive the 9-box placement automatically.</p>
                    </details>
                    <details class="help-faq">
                        <summary>I forgot my password.</summary>
                        <p>Use <strong>Forgot password?</strong> on the login screen to request a reset link. If email isn't configured on this deployment, the page explains where the link went — or an administrator can reset it for you from <strong>Users &amp; Access</strong>.</p>
                    </details>
                    <details class="help-faq">
                        <summary>Who do I contact for support?</summary>
                        <p>Reach out to your HIMS administrator or the hospital IT helpdesk. Include the page you were on and what you expected to happen.</p>
                    </details>
                </div>
            </div>
        </div>

        <div class="topbar-menu-wrap search-menu-wrap">
            <button class="topbar-btn" id="search-btn" title="Search HIMS"
                    aria-haspopup="dialog" aria-expanded="false" aria-controls="global-search-panel">
                <i class="bi bi-search"></i>
            </button>
            <div class="topbar-dropdown search-dropdown" id="global-search-panel" role="dialog"
                 aria-label="Search HIMS" aria-modal="false">
                <div class="global-search-box">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="global-search-input" autocomplete="off"
                           placeholder="Search HIMS" aria-label="Search HIMS"
                           aria-controls="global-search-results" aria-autocomplete="list">
                </div>
                <div class="global-search-status" id="global-search-status" aria-live="polite" style="display:none"></div>
                <div class="global-search-results" id="global-search-results" role="listbox"></div>
            </div>
        </div>

        {{-- Docks/undocks the AI rail, the way VSCode's secondary-sidebar
             button does. Replaces the old floating bubble, which overlapped
             page content and could not be dismissed out of the way. --}}
        <button class="topbar-btn" id="ai-toggle" title="AI Assistant (Ctrl+Shift+A)"
                aria-controls="ai-rail" aria-expanded="false">
            <i class="bi bi-robot"></i>
        </button>
    </div>
</header>

<!-- MAIN -->
<main class="hims-main">
    {{-- data-auto-dismiss is what the timer near the bottom of this file looks
         for. A flash message has been read once it has been seen, so it clears
         itself; a banner a view renders as part of the page does not. --}}
    @if(session('success'))
        <div class="hims-alert success animate-in" data-auto-dismiss>
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="hims-alert error animate-in" data-auto-dismiss>
            <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="hims-alert warning animate-in">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ session('warning') }}
        </div>
    @endif

    <div class="animate-in">
        @yield('content')
    </div>
</main>

{{--
    Modals render here, as a direct child of <body>, never inside <main>.
    The wrapper above carries .animate-in, whose fadeInUp keyframes end on
    `transform: translateY(0)` and are held there by `forwards`. Any transform
    other than none makes an element the containing block for its
    position: fixed descendants — so a backdrop rendered inside that wrapper
    resolves `inset: 0` against the full page content box instead of the
    viewport, and centres itself in the middle of the *page*. On a long page
    that puts the panel far below the fold. Pushing the markup out here is
    what keeps "fixed" meaning fixed.
--}}
@stack('modals')

@include('partials.ai-rail')

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar     = document.getElementById('sidebar');
        const menuToggle  = document.getElementById('menu-toggle');
        const backdrop    = document.getElementById('sidebar-backdrop');

        // ── Mobile sidebar toggle ──
        function openSidebar()  { sidebar.classList.add('open');  backdrop.classList.add('open'); }
        function closeSidebar() { sidebar.classList.remove('open'); backdrop.classList.remove('open'); }
        function toggleSidebar() { sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); }

        if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if (backdrop)   backdrop.addEventListener('click', closeSidebar);

        // ── Topbar dropdowns (notifications + help) ──
        const dropdownPairs = [
            { btn: document.getElementById('notif-btn'),  menu: document.getElementById('notif-dropdown') },
            { btn: document.getElementById('help-btn'),   menu: document.getElementById('help-dropdown')  },
            { btn: document.getElementById('search-btn'), menu: document.getElementById('global-search-panel') },
        ].filter(p => p.btn && p.menu);

        function closeDropdowns(except) {
            dropdownPairs.forEach(p => {
                if (p.menu === except) return;
                p.menu.classList.remove('open');
                p.btn.setAttribute('aria-expanded', 'false');
            });
        }
        dropdownPairs.forEach(p => {
            p.btn.addEventListener('click', e => {
                e.stopPropagation();
                const willOpen = !p.menu.classList.contains('open');
                closeDropdowns(p.menu);
                p.menu.classList.toggle('open', willOpen);
                p.btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen && p.menu.id === 'global-search-panel') {
                    requestAnimationFrame(() => document.getElementById('global-search-input')?.focus());
                }
            });
            p.menu.addEventListener('click', e => e.stopPropagation());
        });
        document.addEventListener('click', () => closeDropdowns(null));

        // Permission-aware global search. The server returns only explicit,
        // access-scoped records and their real module destinations.
        const searchBtn = document.getElementById('search-btn');
        const searchPanel = document.getElementById('global-search-panel');
        const searchInput = document.getElementById('global-search-input');
        const searchStatus = document.getElementById('global-search-status');
        const searchResults = document.getElementById('global-search-results');
        let searchTimer = null;
        let searchRequest = null;
        let activeSearchIndex = -1;

        function openSearch() {
            closeDropdowns(searchPanel);
            searchPanel.classList.add('open');
            searchBtn.setAttribute('aria-expanded', 'true');
            requestAnimationFrame(() => searchInput.focus());
        }

        function searchItems() {
            return Array.from(searchResults.querySelectorAll('.global-search-result'));
        }

        function setActiveSearchResult(index) {
            const items = searchItems();
            if (!items.length) {
                activeSearchIndex = -1;
                return;
            }
            activeSearchIndex = (index + items.length) % items.length;
            items.forEach((item, itemIndex) => {
                const active = itemIndex === activeSearchIndex;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) item.scrollIntoView({ block: 'nearest' });
            });
        }

        function renderSearchResults(data) {
            searchResults.textContent = '';
            activeSearchIndex = -1;
            const results = data.results || [];

            if (!results.length) {
                searchStatus.textContent = '';
                const icon = document.createElement('i');
                icon.className = 'bi bi-search';
                const copy = document.createElement('span');
                copy.append('No results for ');
                const query = document.createElement('strong');
                query.textContent = data.query || searchInput.value.trim();
                copy.append(query, '.');
                searchStatus.append(icon, copy);
                searchStatus.classList.add('empty');
                searchStatus.style.display = '';
                return;
            }

            searchStatus.style.display = 'none';
            searchStatus.classList.remove('empty');
            let currentGroup = null;

            results.forEach(result => {
                if (result.group !== currentGroup) {
                    currentGroup = result.group;
                    const heading = document.createElement('div');
                    heading.className = 'global-search-group';
                    heading.textContent = currentGroup;
                    searchResults.appendChild(heading);
                }

                const link = document.createElement('a');
                link.className = 'global-search-result';
                link.href = result.url;
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');

                const icon = document.createElement('span');
                icon.className = 'global-search-icon';
                const iconGlyph = document.createElement('i');
                iconGlyph.className = 'bi ' + result.icon;
                icon.appendChild(iconGlyph);

                const copy = document.createElement('span');
                copy.className = 'global-search-copy';
                const title = document.createElement('span');
                title.className = 'global-search-title';
                title.textContent = result.title;
                const subtitle = document.createElement('span');
                subtitle.className = 'global-search-subtitle';
                subtitle.textContent = result.subtitle || result.type;
                copy.append(title, subtitle);

                const arrow = document.createElement('i');
                arrow.className = 'bi bi-arrow-up-right global-search-arrow';
                link.append(icon, copy, arrow);
                searchResults.appendChild(link);
            });
        }

        async function runGlobalSearch() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                if (searchRequest) searchRequest.abort();
                searchResults.textContent = '';
                searchStatus.textContent = '';
                searchStatus.style.display = 'none';
                return;
            }

            if (searchRequest) searchRequest.abort();
            searchRequest = new AbortController();
            searchStatus.textContent = '';
            const spinner = document.createElement('span');
            spinner.className = 'global-search-spinner';
            searchStatus.append(spinner, document.createTextNode('Searching HIMS…'));
            searchStatus.style.display = '';
            searchResults.textContent = '';

            try {
                const response = await fetch('{{ route('search') }}?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                    signal: searchRequest.signal,
                });
                if (!response.ok) throw new Error('Search failed.');
                renderSearchResults(await response.json());
            } catch (error) {
                if (error.name === 'AbortError') return;
                searchStatus.textContent = '';
                const icon = document.createElement('i');
                icon.className = 'bi bi-exclamation-circle';
                searchStatus.append(icon, document.createTextNode('Search is unavailable right now.'));
                searchStatus.style.display = '';
            }
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runGlobalSearch, 220);
        });

        searchInput.addEventListener('keydown', event => {
            const items = searchItems();
            if (event.key === 'ArrowDown' && items.length) {
                event.preventDefault();
                setActiveSearchResult(activeSearchIndex + 1);
            } else if (event.key === 'ArrowUp' && items.length) {
                event.preventDefault();
                setActiveSearchResult(activeSearchIndex - 1);
            } else if (event.key === 'Enter' && activeSearchIndex >= 0 && items[activeSearchIndex]) {
                event.preventDefault();
                items[activeSearchIndex].click();
            }
        });

        document.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openSearch();
            }
        });

        // Notifications retain their recent history after being read. Only the
        // unread emphasis and counters change, matching a social activity feed.
        const notifClear = document.getElementById('notif-clear');
        const notifCount = document.getElementById('notif-count');
        const notifHeadingCount = document.getElementById('notif-heading-count');

        function setNotificationCount(count) {
            const unread = Math.max(0, Number(count) || 0);
            if (notifCount) {
                notifCount.textContent = unread > 99 ? '99+' : String(unread);
                notifCount.style.display = unread ? '' : 'none';
            }
            if (notifHeadingCount) {
                notifHeadingCount.textContent = unread ? unread + ' unread' : 'All caught up';
            }
            if (notifClear) notifClear.style.display = unread ? '' : 'none';
        }

        function markNotificationRowRead(row) {
            if (!row.classList.contains('unread')) return;
            row.classList.remove('unread');
            row.classList.add('read');
            row.querySelector('.notif-unread-marker')?.remove();
            setNotificationCount(document.querySelectorAll('.notif-item.unread').length);
        }

        document.querySelectorAll('[data-notification-id]').forEach(row => {
            row.addEventListener('click', async event => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                const destination = row.href;

                try {
                    const response = await fetch(row.dataset.readUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        keepalive: true,
                    });
                    if (response.ok) markNotificationRowRead(row);
                } finally {
                    window.location.assign(destination);
                }
            });
        });

        if (notifClear) {
            notifClear.addEventListener('click', async () => {
                notifClear.disabled = true;
                try {
                    const response = await fetch('{{ route('notifications.read-all') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    });
                    if (response.ok) {
                        document.querySelectorAll('.notif-item.unread').forEach(markNotificationRowRead);
                        setNotificationCount(0);
                    }
                } finally {
                    notifClear.disabled = false;
                }
            });
        }

        // Auto-close the drawer after tapping any nav link (mobile)
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeSidebar();
                closeDropdowns(null);
                // The AI rail is a dock, not a pop-up: Escape closes it only
                // while it is overlaying the page, matching how the mobile
                // nav drawer behaves. On desktop it stays put.
                if (railOverlays()) closeRail();
            }
        });
        // Only the flash messages this layout renders self-dismiss, which is why
        // they are marked rather than selected by class. `.hims-alert` is also the
        // house style for permanent explanatory banners — the frozen-review notice,
        // the compliance rule explainers, the AI-unavailable box — and an
        // unqualified `.hims-alert` selector deleted those four seconds after
        // load, on every page that had one. Opt-in is the safe direction: a new
        // flash that forgets the attribute merely stays on screen, whereas a new
        // banner forgetting an opt-out would vanish silently.
        setTimeout(() => {
            document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                el.style.transition = 'all .4s ease';
                setTimeout(() => el.remove(), 400);
            });
        }, 4000);

        /* ══ AI ASSISTANT RAIL ══════════════════════════════════════════
           A dock, not a pop-up. Three pieces of state:
             open        whether the rail is showing        (localStorage)
             width       how wide the user dragged it       (localStorage)
             sessionId   which conversation is loaded       (localStorage)
           All three are persisted because this is a multi-page app: every
           navigation rebuilds the DOM, and the rail has to come back exactly
           as the user left it or it feels like it closed itself.

           Below RAIL_PUSH_MIN the rail overlays the page instead of pushing
           it, because a 320px dock on top of a 1024px viewport leaves the
           tables unreadable. --hims-ai-offset is what the page reflows to,
           and it is deliberately 0 in overlay mode. */
        const rail      = document.getElementById('ai-rail');
        const railToggle = document.getElementById('ai-toggle');
        const railBackdrop = document.getElementById('ai-rail-backdrop');
        const resizer   = document.getElementById('ai-rail-resizer');
        const closeBtn  = document.getElementById('ai-close-btn');
        const newChatBtn = document.getElementById('ai-new-chat');
        const historyBtn = document.getElementById('ai-history-btn');
        const sessionsPane = document.getElementById('ai-sessions');
        const sessionList  = document.getElementById('ai-session-list');
        const sessionsEmpty = document.getElementById('ai-sessions-empty');
        const clearAllBtn = document.getElementById('ai-clear-all');
        const input     = document.getElementById('ai-input');
        const sendBtn   = document.getElementById('ai-send-btn');
        const messages  = document.getElementById('ai-messages');
        const welcome   = document.getElementById('ai-welcome');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const OPEN_KEY    = 'hims_ai_open';
        const WIDTH_KEY   = 'hims_ai_width';
        const SESSION_KEY = 'hims_ai_session';
        const DRAFT_KEY   = 'hims_ai_draft';
        const HISTORY_KEY = 'hims_ai_history_open';
        const RAIL_PUSH_MIN = 1100;   // keep in step with hims.css
        const MIN_W = 280, MAX_W = 620;

        let sessionId  = localStorage.getItem(SESSION_KEY) || null;
        let sessionsLoaded = false;

        // A global-search result can target one saved conversation on any page.
        // Put it into the same persisted state the rail normally uses, then
        // clean the URL so a refresh does not keep overriding the user's choice.
        const requestedAiSession = new URLSearchParams(window.location.search).get('ai_session');
        if (requestedAiSession) {
            sessionId = requestedAiSession;
            localStorage.setItem(SESSION_KEY, sessionId);
            localStorage.setItem(OPEN_KEY, '1');
            localStorage.setItem(HISTORY_KEY, '1');
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('ai_session');
            window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
        }

        const root = document.documentElement;
        function railOpen()     { return rail.classList.contains('open'); }
        function railOverlays() { return window.innerWidth < RAIL_PUSH_MIN; }

        /** Width lives on :root so the topbar and main can both read it. */
        function applyWidth(px) {
            const w = Math.min(MAX_W, Math.max(MIN_W, Math.round(px)));
            root.style.setProperty('--hims-ai-w', w + 'px');
            localStorage.setItem(WIDTH_KEY, String(w));
            applyOffset();
        }
        /** How much room the page gives up. Zero unless the rail pushes. */
        function applyOffset() {
            const push = railOpen() && !railOverlays();
            root.style.setProperty('--hims-ai-offset',
                push ? 'var(--hims-ai-w)' : '0px');
            // Lets the stylesheet loosen anything that assumed a full-width
            // main column — wide tables in particular.
            document.body.classList.toggle('ai-rail-docked', push);
        }

        const savedWidth = parseInt(localStorage.getItem(WIDTH_KEY) || '', 10);
        if (savedWidth) applyWidth(savedWidth);

        function openRail(skipFocus) {
            rail.classList.add('open');
            rail.setAttribute('aria-hidden', 'false');
            railToggle.setAttribute('aria-expanded', 'true');
            railToggle.classList.add('active');
            localStorage.setItem(OPEN_KEY, '1');
            applyOffset();
            if (railOverlays()) railBackdrop.classList.add('open');
            loadConversation();
            if (!sessionsPane.hasAttribute('hidden')) loadSessions();
            if (!skipFocus) input.focus();
        }
        function closeRail() {
            rail.classList.remove('open');
            rail.setAttribute('aria-hidden', 'true');
            railToggle.setAttribute('aria-expanded', 'false');
            railToggle.classList.remove('active');
            railBackdrop.classList.remove('open');
            localStorage.removeItem(OPEN_KEY);
            applyOffset();
        }
        function toggleRail() { railOpen() ? closeRail() : openRail(); }

        railToggle.addEventListener('click', toggleRail);
        closeBtn.addEventListener('click', closeRail);
        railBackdrop.addEventListener('click', closeRail);

        // Ctrl+Shift+A, the way VSCode toggles its secondary sidebar.
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && e.shiftKey && (e.key === 'A' || e.key === 'a')) {
                e.preventDefault();
                toggleRail();
            }
        });

        // Crossing the breakpoint flips push/overlay, so the offset and the
        // backdrop both have to be recomputed — otherwise a rail opened on a
        // wide screen keeps squeezing the page after a resize down.
        window.addEventListener('resize', () => {
            applyOffset();
            railBackdrop.classList.toggle('open', railOpen() && railOverlays());
        });
/* ── Resize by dragging the rail's left edge ───────────────── */
        // Pointer events rather than mouse events so a stylus or touch drag
        // works too, and setPointerCapture keeps the drag alive when the
        // cursor crosses an iframe or leaves the window.
        let dragging = false;
        resizer.addEventListener('pointerdown', e => {
            dragging = true;
            resizer.setPointerCapture(e.pointerId);
            document.body.classList.add('ai-resizing');
            e.preventDefault();
        });
        document.addEventListener('pointermove', e => {
            if (dragging) applyWidth(window.innerWidth - e.clientX);
        });
        document.addEventListener('pointerup', () => {
            if (!dragging) return;
            dragging = false;
            document.body.classList.remove('ai-resizing');
        });
        // Keyboard equivalent, so the separator is not mouse-only.
        resizer.addEventListener('keydown', e => {
            const current = rail.getBoundingClientRect().width;
            if (e.key === 'ArrowLeft')  { applyWidth(current + 24); e.preventDefault(); }
            if (e.key === 'ArrowRight') { applyWidth(current - 24); e.preventDefault(); }
        });

        /* ── Conversation list ─────────────────────────────────────── */
        historyBtn.addEventListener('click', () => {
            const show = sessionsPane.hasAttribute('hidden');
            sessionsPane.toggleAttribute('hidden', !show);
            historyBtn.setAttribute('aria-expanded', show ? 'true' : 'false');
            historyBtn.classList.toggle('active', show);
            localStorage.setItem(HISTORY_KEY, show ? '1' : '0');
            if (show) loadSessions();
        });

        // History was previously hidden on every page load, which made the
        // stored sessions look as though they had been deleted. Keep it visible
        // by default and only honour an explicit collapse from this browser.
        if (localStorage.getItem(HISTORY_KEY) === '0') {
            sessionsPane.setAttribute('hidden', '');
            historyBtn.setAttribute('aria-expanded', 'false');
            historyBtn.classList.remove('active');
        }

        function setTitle(text) {
            const label = document.getElementById('ai-rail-title');
            label.innerHTML = '<i class="bi bi-robot"></i> ';
            label.appendChild(document.createTextNode(text || 'AI Assistant'));
            label.title = text || 'AI Assistant';
        }

        async function loadSessions() {
            try {
                const res  = await fetch('{{ route("ai.sessions") }}', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Conversation history request failed.');
                const data = await res.json();
                renderSessions(data.sessions || []);
                sessionsLoaded = true;
            } catch (e) {
                sessionsEmpty.textContent = 'Could not load conversations.';
                sessionsEmpty.style.display = '';
            }
        }

        function renderSessions(list) {
            sessionList.textContent = '';
            sessionsEmpty.textContent = 'No earlier conversations.';
            sessionsEmpty.style.display = list.length ? 'none' : '';

            list.forEach(s => {
                const row = document.createElement('div');
                row.className = 'ai-session' + (s.id === sessionId ? ' active' : '');

                // textContent, never innerHTML: titles are the user's own first
                // question echoed back, so they must not be parsed as markup.
                const name = document.createElement('button');
                name.type = 'button';
                name.className = 'ai-session-name';
                name.textContent = s.title || 'New chat';
                name.title = s.title || 'New chat';
                name.addEventListener('click', () => selectSession(s.id));

                const rename = iconBtn('bi-pencil', 'Rename', async () => {
                    const next = prompt('Rename conversation', s.title || '');
                    if (next === null) return;
                    const title = next.trim();
                    if (!title) return;
                    await fetch('/ai/sessions/' + encodeURIComponent(s.id), {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ title }),
                    });
                    if (s.id === sessionId) setTitle(title);
                    loadSessions();
                });

                const del = iconBtn('bi-trash', 'Delete', async () => {
                    if (!confirm('Delete this conversation?')) return;
                    await fetch('/ai/sessions/' + encodeURIComponent(s.id), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    });
                    // Deleting the open conversation leaves nothing loaded, so
                    // drop back to a blank chat rather than a stale transcript.
                    if (s.id === sessionId) startNewChat(true);
                    loadSessions();
                });

                row.append(name, rename, del);
                sessionList.appendChild(row);
            });
        }

        function iconBtn(icon, label, handler) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ai-session-act';
            b.title = label;
            b.setAttribute('aria-label', label);
            b.innerHTML = '<i class="bi ' + icon + '"></i>';
            b.addEventListener('click', e => { e.stopPropagation(); handler(); });
            return b;
        }

        clearAllBtn.addEventListener('click', async () => {
            if (!confirm('Delete every conversation? This cannot be undone.')) return;
            await fetch('{{ route("ai.history.clear") }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            renderSessions([]);
            startNewChat(true);
        });

        /* ── Transcript ────────────────────────────────────────────── */
        function appendMsg(text, role, variant) {
            const div = document.createElement('div');
            div.className = 'ai-msg ' + role + (variant ? ' ' + variant : '');
            div.textContent = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }

        function resetTranscript() {
            messages.querySelectorAll('.ai-msg:not(#ai-welcome)').forEach(el => el.remove());
            welcome.style.display = '';
        }

        function startNewChat(silent) {
            // No POST /ai/sessions here: the row would be created before the
            // user types anything, leaving an untitled empty conversation in
            // the list if they walk away. query() creates it on first send.
            sessionId = null;
            localStorage.removeItem(SESSION_KEY);
            resetTranscript();
            setTitle(null);
            if (sessionsLoaded) renderSessionActive();
            if (!silent) input.focus();
        }

        function renderSessionActive() {
            sessionList.querySelectorAll('.ai-session').forEach(el => el.classList.remove('active'));
        }

        newChatBtn.addEventListener('click', () => startNewChat(false));

        async function selectSession(id) {
            sessionId = id;
            localStorage.setItem(SESSION_KEY, id);
            await loadConversation(true);
            if (sessionsLoaded) loadSessions();
        }

        /**
         * Paint the stored transcript into the rail.
         *
         * With no session id it asks /ai/history, which returns the most
         * recently used conversation — so opening the rail on a fresh browser
         * resumes where the user left off instead of showing a blank panel.
         */
        let conversationLoaded = false;
        async function loadConversation(force) {
            if (conversationLoaded && !force) return;
            conversationLoaded = true;
            resetTranscript();

            const url = sessionId
                ? '/ai/sessions/' + encodeURIComponent(sessionId) + '/messages'
                : '{{ route("ai.history") }}';

            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (res.status === 404) {      // deleted in another tab
                    startNewChat(true);
                    return;
                }
                if (!res.ok) throw new Error('Conversation request failed.');
                const data = await res.json();
                if (data.session && data.session.id) {
                    sessionId = data.session.id;
                    localStorage.setItem(SESSION_KEY, sessionId);
                    setTitle(data.session.title);
                }
                if (data.messages && data.messages.length) {
                    welcome.style.display = 'none';
                    data.messages.forEach(m => appendMsg(m.message, m.role));
                }
            } catch (e) {
                conversationLoaded = false;
                /* leave the welcome message showing */
            }
        }

        /* ── Composer ──────────────────────────────────────────────── */
        const savedDraft = localStorage.getItem(DRAFT_KEY) || '';
        if (savedDraft) { input.value = savedDraft; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; }
        input.addEventListener('input', () => {
            localStorage.setItem(DRAFT_KEY, input.value);
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        });

        async function sendQuery() {
            const text = input.value.trim();
            if (!text) return;

            welcome.style.display = 'none';
            input.value = '';
            input.style.height = 'auto';
            localStorage.removeItem(DRAFT_KEY);
            sendBtn.disabled = true;
            appendMsg(text, 'user');

            const thinking = appendMsg('Thinking…', 'thinking');

            try {
                const res = await fetch('{{ route("ai.query") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    // session_id is what turns the reply into a follow-up:
                    // the controller replays this conversation's earlier turns
                    // to the model. Omitting it starts a fresh one.
                    body: JSON.stringify({ query: text, session_id: sessionId }),
                });
                const data = await res.json();
                thinking.remove();
                // A reply that changed the database is tinted; a plain answer
                // carries neither field and renders exactly as before. Replaying
                // a transcript has no such data, so history stays untinted.
                const variant = data.pending_confirm ? 'did-pending'
                    : data.action_status === 'ok' ? 'did-ok'
                    : data.action_status === 'error' ? 'did-error'
                    : null;
                appendMsg(data.response ?? 'No response.', 'ai', variant);

                if (data.session_id) {
                    const isNew = data.session_id !== sessionId;
                    sessionId = data.session_id;
                    localStorage.setItem(SESSION_KEY, sessionId);
                    setTitle(data.title);
                    // Titles and ordering both change on send, so the list is
                    // stale the moment a message lands.
                    if (sessionsLoaded && (isNew || !sessionsPane.hasAttribute('hidden'))) loadSessions();
                }
            } catch (err) {
                thinking.remove();
                appendMsg('⚠️ Connection error. Please try again.', 'ai');
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        }

        sendBtn.addEventListener('click', sendQuery);
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendQuery(); }
        });

        // Reopen where the user left off. skipFocus so arriving on a new page
        // does not steal the caret from the page's own first field.
        if (localStorage.getItem(OPEN_KEY)) openRail(true);
    });
</script>
<script src="{{ asset('js/hims-select.js') }}?v={{ file_exists(public_path('js/hims-select.js')) ? filemtime(public_path('js/hims-select.js')) : 1 }}"></script>
@stack('scripts')
</body>
</html>
