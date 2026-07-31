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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/hims.css') }}">
    @stack('head')
</head>
<body>

<!-- SIDEBAR -->
<aside class="hims-sidebar" id="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <div class="brand-icon">🏥</div>
        <div class="brand-text">
            <span class="brand-name">HIMS</span>
            <span class="brand-sub">Performance & Development</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Overview</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <div class="sidebar-section-label">HR Modules</div>
        <a href="{{ route('performance.index') }}" class="sidebar-link {{ request()->routeIs('performance.*') ? 'active' : '' }}">
            <span class="nav-icon">📋</span> Performance
        </a>
        <a href="{{ route('competency.index') }}" class="sidebar-link {{ request()->routeIs('competency.*') ? 'active' : '' }}">
            <span class="nav-icon">🎯</span> Competency
        </a>
        <a href="{{ route('learning.index') }}" class="sidebar-link {{ request()->routeIs('learning.*') ? 'active' : '' }}">
            <span class="nav-icon">📚</span> Learning
        </a>
        <a href="{{ route('training.index') }}" class="sidebar-link {{ request()->routeIs('training.*') ? 'active' : '' }}">
            <span class="nav-icon">🎓</span> Training
        </a>
        <a href="{{ route('succession.index') }}" class="sidebar-link {{ request()->routeIs('succession.*') ? 'active' : '' }}">
            <span class="nav-icon">🏆</span> Succession
        </a>
        <a href="{{ route('recognition.index') }}" class="sidebar-link {{ request()->routeIs('recognition.*') ? 'active' : '' }}">
            <span class="nav-icon">⭐</span> Recognition
        </a>

        <div class="sidebar-section-label">Admin</div>
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <span class="nav-icon">👥</span> Employees
        </a>
        <a href="{{ route('departments.index') }}" class="sidebar-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
            <span class="nav-icon">🏢</span> Departments
        </a>
        <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <span class="nav-icon">🔐</span> Users & Access
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile.edit') }}" class="sidebar-user">
            <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</div>
            <div class="user-info">
                <span class="user-name">{{ Auth::user()->name ?? 'User' }}</span>
                <span class="user-role">{{ Auth::user()->role ?? 'Staff' }}</span>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <a href="#" onclick="this.closest('form').submit()" class="sidebar-link" style="margin-top:4px">
                <span class="nav-icon">🚪</span> Logout
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
        <button class="topbar-btn" title="Notifications">
            <i class="bi bi-bell"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="topbar-btn" title="Search">
            <i class="bi bi-search"></i>
        </button>
        <button class="topbar-btn" title="Help">
            <i class="bi bi-question-circle"></i>
        </button>
    </div>
</header>

<!-- MAIN -->
<main class="hims-main">
    @if(session('success'))
        <div class="hims-alert success animate-in">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="hims-alert error animate-in">
            <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
        </div>
    @endif

    <div class="animate-in">
        @yield('content')
    </div>
</main>

<!-- ══ FLOATING GEMINI AI BUBBLE ══ -->
<style>
#ai-bubble {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--hims-primary), var(--hims-primary-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(22,163,74,.45);
    z-index: 1000;
    transition: transform .2s, box-shadow .2s;
    font-size: 22px;
    user-select: none;
}
#ai-bubble:hover { transform: scale(1.12); box-shadow: 0 6px 28px rgba(22,163,74,.6); }
#ai-bubble.open { transform: scale(1.08) rotate(15deg); }

#ai-panel {
    position: fixed;
    bottom: 96px;
    right: 28px;
    width: 360px;
    max-height: 520px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 12px 48px rgba(0,0,0,.18);
    z-index: 999;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(.96);
    opacity: 0;
    pointer-events: none;
    transition: all .25s cubic-bezier(.34,1.56,.64,1);
}
#ai-panel.open {
    transform: translateY(0) scale(1);
    opacity: 1;
    pointer-events: all;
}

#ai-panel-header {
    background: linear-gradient(135deg, var(--hims-primary), var(--hims-primary-dark));
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
#ai-panel-header .ai-close {
    margin-left: auto;
    cursor: pointer;
    opacity: .8;
    font-size: 18px;
    line-height: 1;
    background: none;
    border: none;
    color: #fff;
}
#ai-panel-header .ai-close:hover { opacity: 1; }

#ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8fdf9;
}

.ai-msg {
    max-width: 86%;
    padding: 9px 13px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
    animation: fadeInUp .2s ease;
}
.ai-msg.user {
    background: var(--hims-primary);
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.ai-msg.ai {
    background: #fff;
    color: var(--hims-text-dark);
    align-self: flex-start;
    border: 1px solid var(--hims-border);
    border-bottom-left-radius: 4px;
    white-space: pre-wrap;
}
.ai-msg.thinking {
    background: #fff;
    border: 1px dashed var(--hims-border);
    color: #9ca3af;
    align-self: flex-start;
    font-style: italic;
}

#ai-input-row {
    padding: 12px;
    border-top: 1px solid var(--hims-border);
    display: flex;
    gap: 8px;
    background: #fff;
    flex-shrink: 0;
}
#ai-input-row textarea {
    flex: 1;
    resize: none;
    border: 1px solid var(--hims-border);
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    max-height: 90px;
    line-height: 1.5;
}
#ai-input-row textarea:focus { border-color: var(--hims-primary); }
#ai-send-btn {
    width: 38px;
    height: 38px;
    background: var(--hims-primary);
    border: none;
    border-radius: 10px;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
    font-size: 16px;
}
#ai-send-btn:hover { background: var(--hims-primary-dark); }
#ai-send-btn:disabled { background: #9ca3af; cursor: not-allowed; }
</style>

<!-- Bubble toggle button -->
<div id="ai-bubble" title="Ask Gemini AI">🤖</div>

<!-- Slide-up chat panel -->
<div id="ai-panel">
    <div id="ai-panel-header">
        <span>🤖</span>
        <span>Gemini AI Assistant</span>
        <span style="font-size:11px;opacity:.7;font-weight:400">EN / Tagalog</span>
        <button id="ai-clear-btn" title="Clear chat" style="background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:12px;padding:2px 6px;border-radius:4px;margin-left:auto;transition:color .15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.7)'">🗑 Clear</button>
        <button class="ai-close" id="ai-close-btn" title="Close">✕</button>
    </div>
    <div id="ai-messages">
        <div id="ai-welcome" class="ai-msg ai" style="display:none">Kamusta! I'm your HIMS AI assistant. Ask me about performance, competency, training, succession, or anything HR-related. 🏥</div>
    </div>
    <div id="ai-input-row">
        <textarea id="ai-input" placeholder="Ask in English or Tagalog…" rows="1"></textarea>
        <button id="ai-send-btn" title="Send"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

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

        // Auto-close the drawer after tapping any nav link (mobile)
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeSidebar();
                closePanel();
            }
        });
        setTimeout(() => {
            document.querySelectorAll('.hims-alert').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                el.style.transition = 'all .4s ease';
                setTimeout(() => el.remove(), 400);
            });
        }, 4000);

        // ── AI Bubble logic ──
        const bubble    = document.getElementById('ai-bubble');
        const panel     = document.getElementById('ai-panel');
        const closeBtn  = document.getElementById('ai-close-btn');
        const clearBtn  = document.getElementById('ai-clear-btn');
        const input     = document.getElementById('ai-input');
        const sendBtn   = document.getElementById('ai-send-btn');
        const messages  = document.getElementById('ai-messages');
        const welcome   = document.getElementById('ai-welcome');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const STORE_KEY  = 'hims_ai_open';
        const DRAFT_KEY  = 'hims_ai_draft';
        let historyLoaded = false;

        function openPanel(skipFocus) {
            panel.classList.add('open');
            bubble.classList.add('open');
            sessionStorage.setItem(STORE_KEY, '1');
            if (!historyLoaded) loadHistory();
            if (!skipFocus) input.focus();
        }
        function closePanel() {
            panel.classList.remove('open');
            bubble.classList.remove('open');
            sessionStorage.removeItem(STORE_KEY);
        }
        function togglePanel() { panel.classList.contains('open') ? closePanel() : openPanel(); }

        bubble.addEventListener('click', togglePanel);
        closeBtn.addEventListener('click', closePanel);

        // Restore draft text the user was typing before navigation
        const savedDraft = sessionStorage.getItem(DRAFT_KEY) || '';
        if (savedDraft) { input.value = savedDraft; input.style.height = Math.min(input.scrollHeight, 88) + 'px'; }
        input.addEventListener('input', () => {
            sessionStorage.setItem(DRAFT_KEY, input.value);
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 88) + 'px';
        });

        // Auto-reopen if it was open before navigation
        if (sessionStorage.getItem(STORE_KEY)) openPanel(true);

        function appendMsg(text, role) {
            const div = document.createElement('div');
            div.className = 'ai-msg ' + role;
            div.textContent = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }

        async function loadHistory() {
            historyLoaded = true;
            try {
                const res  = await fetch('{{ route("ai.history") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => appendMsg(m.message, m.role));
                } else {
                    welcome.style.display = '';
                }
            } catch (e) {
                welcome.style.display = '';
            }
        }

        clearBtn.addEventListener('click', async () => {
            if (!confirm('Clear all chat history?')) return;
            await fetch('{{ route("ai.history.clear") }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            messages.querySelectorAll('.ai-msg:not(#ai-welcome)').forEach(el => el.remove());
            welcome.style.display = '';
            historyLoaded = true;
        });

        async function sendQuery() {
            const text = input.value.trim();
            if (!text) return;

            welcome.style.display = 'none';
            input.value = '';
            input.style.height = 'auto';
            sessionStorage.removeItem(DRAFT_KEY);
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
                    body: JSON.stringify({ query: text }),
                });
                const data = await res.json();
                thinking.remove();
                appendMsg(data.response ?? 'No response.', 'ai');
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
    });
</script>
@stack('scripts')
</body>
</html>
