<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

<!-- TOPBAR -->
<header class="hims-topbar">
    <div class="topbar-left">
        <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
        <p class="page-breadcrumb">@yield('breadcrumb', 'HIMS / Dashboard')</p>
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

<script>
    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') sidebar.classList.remove('open');
        });
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.hims-alert').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                el.style.transition = 'all .4s ease';
                setTimeout(() => el.remove(), 400);
            });
        }, 4000);
    });
</script>
@stack('scripts')
</body>
</html>
