<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR3 — Performance &amp; Development | HIMS</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('partials.app-css')
    <style>
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: #f6faf7; color: #0f2e1c; }
        .lp-nav { display: flex; align-items: center; justify-content: space-between; padding: 18px 6vw; background: #fff; border-bottom: 1px solid #e3efe7; position: sticky; top: 0; z-index: 5; }
        .lp-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 18px; color: #14532d; text-decoration: none; }
        .lp-brand .mark { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #16a34a, #15803d); display: grid; place-items: center; color: #fff; font-size: 19px; }
        .lp-brand small { display: block; font-weight: 600; font-size: 11px; color: #4b6b57; letter-spacing: .04em; text-transform: uppercase; }
        .lp-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease; }
        .lp-btn.primary { background: #16a34a; color: #fff; box-shadow: 0 6px 18px rgba(22,163,74,.25); }
        .lp-btn.primary:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(22,163,74,.3); }
        .lp-btn.ghost { color: #15803d; border: 1px solid #bbf7d0; background: #fff; }
        .lp-hero { padding: 72px 6vw 56px; display: grid; grid-template-columns: 1fr; gap: 48px; align-items: center; max-width: 1240px; margin: 0 auto; }
        .lp-eyebrow { display: inline-flex; align-items: center; gap: 8px; background: #dcfce7; color: #166534; font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 999px; letter-spacing: .03em; }
        .lp-hero h1 { font-size: clamp(30px, 4vw, 46px); line-height: 1.12; margin: 18px 0 14px; color: #052e16; letter-spacing: -.02em; }
        .lp-hero p.lead { font-size: 17px; line-height: 1.6; color: #3f5f4b; margin: 0 0 28px; max-width: 560px; }
        .lp-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .lp-section { padding: 24px 6vw 64px; max-width: 1240px; margin: 0 auto; }
        .lp-section h2 { font-size: 24px; margin: 0 0 6px; color: #052e16; }
        .lp-section > p { color: #4b6b57; margin: 0 0 26px; }
        .lp-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .lp-card { background: #fff; border: 1px solid #e3efe7; border-radius: 16px; padding: 22px; }
        .lp-card .ic { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; margin-bottom: 12px; }
        .lp-card h4 { margin: 0 0 6px; font-size: 16px; color: #052e16; }
        .lp-card p { margin: 0; color: #4b6b57; font-size: 14px; line-height: 1.55; }
        .lp-notice { max-width: 1240px; margin: 18px auto 0; padding: 0 6vw; }
        .lp-notice div { display: flex; gap: 10px; align-items: center; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 600; }
        .lp-notice div.err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .lp-foot { border-top: 1px solid #e3efe7; background: #fff; padding: 22px 6vw; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; color: #4b6b57; font-size: 13px; }
        .lp-foot a { color: #15803d; text-decoration: none; font-weight: 600; }
        @media (max-width: 900px) { .lp-hero { padding-top: 44px; } .lp-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .lp-grid { grid-template-columns: 1fr; } .lp-brand small { display: none; } }
    </style>
</head>
<body>
    <nav class="lp-nav">
        <a class="lp-brand" href="{{ route('landing') }}">
            <span class="mark"><i class="bi bi-heart-pulse"></i></span>
            <span>HIMS <small>HR3 · Performance &amp; Development</small></span>
        </a>
        @auth
            <a class="lp-btn primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Go to dashboard</a>
        @else
            <a class="lp-btn primary" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
        @endauth
    </nav>

    @if(request('timeout'))
        <div class="lp-notice" role="status"><div><i class="bi bi-clock-history"></i> You were signed out after {{ (int) ceil(\App\Http\Middleware\EnforceSessionTimeout::idleTimeoutSeconds() / 60) }} minutes without activity to protect staff records. Sign in again to continue.</div></div>
    @elseif(request('reason') === 'expired')
        <div class="lp-notice" role="status"><div class="err"><i class="bi bi-calendar-x"></i> Your access period has ended. If your placement or orientation continues, ask HR or the Training Officer to extend it.</div></div>
    @elseif(request('reason') === 'deactivated')
        <div class="lp-notice" role="status"><div class="err"><i class="bi bi-person-x"></i> Your account has been deactivated. Please contact HR or your system administrator.</div></div>
    @endif

    <header class="lp-hero">
        <div>
            <span class="lp-eyebrow"><i class="bi bi-hospital"></i> Hospital Human Resources · HR3</span>
            <h1>Grow every caregiver — from first review to next role.</h1>
            <p class="lead">Performance reviews, competency gaps, CPD and training, succession readiness and staff recognition in one place. Staff accounts and records come from the hospital's HR1 and HR2 systems.</p>
            <div class="lp-actions">
                @auth
                    <a class="lp-btn primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Open my dashboard</a>
                @else
                    <a class="lp-btn primary" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i> Sign in to HR3</a>
                @endauth
                <a class="lp-btn ghost" href="#modules"><i class="bi bi-grid"></i> What's inside</a>
            </div>
        </div>
    </header>

    <section class="lp-section" id="modules">
        <h2>Modules</h2>
        <p>Everything a hospital HR team needs to develop and keep its people.</p>
        <div class="lp-grid">
            <div class="lp-card"><div class="ic" style="background:#dcfce7;color:#15803d"><i class="bi bi-clipboard2-check"></i></div><h4>Performance</h4><p>Review cycles, weighted KPIs, goals and improvement plans, with the employee's own response on record.</p></div>
            <div class="lp-card"><div class="ic" style="background:#e0f2fe;color:#0369a1"><i class="bi bi-bullseye"></i></div><h4>Competency &amp; gaps</h4><p>Required vs. assessed proficiency per role, with every gap calculated step by step and ranked by priority.</p></div>
            <div class="lp-card"><div class="ic" style="background:#fef3c7;color:#b45309"><i class="bi bi-mortarboard"></i></div><h4>Learning &amp; CPD</h4><p>Courses, mandatory training and CPD hours — counted as qualified only when attendance or completion is on record.</p></div>
            <div class="lp-card"><div class="ic" style="background:#ede9fe;color:#6d28d9"><i class="bi bi-calendar-event"></i></div><h4>Training</h4><p>Sessions, venues, check-in and feedback for in-house clinical and compliance training.</p></div>
            <div class="lp-card"><div class="ic" style="background:#fce7f3;color:#be185d"><i class="bi bi-ladder"></i></div><h4>Succession</h4><p>Critical positions, ready-now candidates and a qualification checklist before anyone is promoted.</p></div>
            <div class="lp-card"><div class="ic" style="background:#fff7ed;color:#c2410c"><i class="bi bi-award"></i></div><h4>Recognition</h4><p>Service milestones, excellence awards, verifiable digital certificates and badges on every profile.</p></div>
        </div>
    </section>

    <footer class="lp-foot">
        <span>&copy; {{ now()->year }} HIMS · HR3 Performance &amp; Development</span>
        <span><a href="{{ route('terms-of-service') }}">Terms of Service</a> · Signed-in sessions end after {{ (int) ceil(\App\Http\Middleware\EnforceSessionTimeout::idleTimeoutSeconds() / 60) }} minutes of inactivity</span>
    </footer>
</body>
</html>
