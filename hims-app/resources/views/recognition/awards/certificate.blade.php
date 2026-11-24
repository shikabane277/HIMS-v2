<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $award->certificate_title }} — {{ $award->first_name }} {{ $award->last_name }}</title>
    <meta name="robots" content="noindex">
    <meta property="og:title" content="{{ $award->first_name }} {{ $award->last_name }} received the {{ $award->award_name }}">
    <meta property="og:description" content="Verified award certificate {{ $award->certificate_code }}">
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --c: {{ $award->badge_color }}; --ink: #0f172a; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #eef2f6; color: #1e293b; display: flex; flex-direction: column; align-items: center; padding: 32px 16px; min-height: 100vh; }
        .bar { width: 100%; max-width: 1040px; display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
        .verify { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; padding: 10px 14px; border-radius: 10px; }
        .verify.ok { background: #dcfce7; color: #166534; }
        .verify.bad { background: #fee2e2; color: #991b1b; }
        .btns { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 9px; font-weight: 600; font-size: 13px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; text-decoration: none; cursor: pointer; }
        .btn.primary { background: #16a34a; border-color: #16a34a; color: #fff; }
        .cert { width: 100%; max-width: 1040px; aspect-ratio: 1.414 / 1; background: #fff; position: relative; padding: 5.5% 7%; box-shadow: 0 20px 60px rgba(15,23,42,.15); display: flex; flex-direction: column; align-items: center; text-align: center; overflow: hidden; }
        .cert::before { content: ''; position: absolute; inset: 14px; border: 2px solid color-mix(in srgb, var(--c) 60%, #fff); pointer-events: none; }
        .cert::after { content: ''; position: absolute; inset: 22px; border: 1px solid color-mix(in srgb, var(--c) 30%, #fff); pointer-events: none; }
        .corner { position: absolute; width: 180px; height: 180px; background: radial-gradient(circle at center, color-mix(in srgb, var(--c) 18%, transparent), transparent 70%); }
        .corner.tl { top: -60px; left: -60px; } .corner.br { bottom: -60px; right: -60px; }
        .org { font-weight: 700; letter-spacing: .18em; text-transform: uppercase; font-size: clamp(9px, 1.2vw, 13px); color: #475569; }
        .org-sub { font-size: clamp(8px, 1vw, 11.5px); color: #64748b; margin-top: 3px; }
        .title { font-family: 'Cinzel', serif; font-weight: 800; font-size: clamp(20px, 3.6vw, 40px); color: var(--ink); margin-top: 3.5%; letter-spacing: .04em; }
        .presented { font-size: clamp(10px, 1.3vw, 14px); color: #64748b; margin-top: 2%; font-style: italic; }
        .name { font-family: 'Cinzel', serif; font-weight: 700; font-size: clamp(22px, 4.2vw, 48px); color: var(--c); margin-top: 1.5%; border-bottom: 2px solid color-mix(in srgb, var(--c) 35%, #fff); padding: 0 24px 6px; }
        .role { font-size: clamp(10px, 1.2vw, 13.5px); color: #475569; margin-top: 1%; }
        .award { font-weight: 700; font-size: clamp(13px, 1.8vw, 20px); color: var(--ink); margin-top: 3%; display: flex; align-items: center; gap: 10px; }
        .seal { width: clamp(38px, 5.5vw, 64px); height: clamp(38px, 5.5vw, 64px); border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: clamp(16px, 2.6vw, 28px); background: radial-gradient(circle at 30% 25%, color-mix(in srgb, var(--c) 70%, #fff), var(--c)); box-shadow: 0 0 0 4px color-mix(in srgb, var(--c) 22%, #fff); }
        .body { font-size: clamp(10px, 1.35vw, 15px); line-height: 1.6; color: #334155; margin-top: 2%; max-width: 78%; }
        .foot { margin-top: auto; width: 100%; display: grid; grid-template-columns: 1fr auto 1fr; align-items: end; gap: 20px; }
        .sign { text-align: center; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 0 auto 6px; width: 80%; }
        .sign .who { font-weight: 700; font-size: clamp(9px, 1.15vw, 13px); }
        .sign .what { font-size: clamp(8px, 1vw, 11.5px); color: #64748b; }
        .code { font-size: clamp(8px, .95vw, 11px); color: #64748b; text-align: center; }
        .code strong { display: block; font-family: ui-monospace, monospace; color: #0f172a; font-size: clamp(9px, 1.05vw, 12.5px); }
        .revoked-stamp { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); border: 6px solid #dc2626; color: #dc2626; font-family: 'Cinzel', serif; font-weight: 800; font-size: clamp(30px, 6vw, 72px); padding: 6px 28px; opacity: .8; letter-spacing: .1em; }
        .toast { position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%); background: #0f172a; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; opacity: 0; transition: opacity .2s; }
        .toast.show { opacity: 1; }
        @page { size: A4 landscape; margin: 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .bar, .toast { display: none !important; }
            .cert { box-shadow: none; max-width: none; width: 297mm; height: 210mm; aspect-ratio: auto; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="bar">
        @if($award->status === 'issued')
            <div class="verify ok"><i class="bi bi-patch-check-fill"></i> Verified — issued by HIMS HR3 on {{ \Carbon\Carbon::parse($award->awarded_on)->format('F j, Y') }}</div>
        @else
            <div class="verify bad"><i class="bi bi-x-octagon-fill"></i> This award was revoked{{ $award->revoked_at ? ' on '.\Carbon\Carbon::parse($award->revoked_at)->format('F j, Y') : '' }}. Reason: {{ $award->revoked_reason }}</div>
        @endif
        <div class="btns">
            <button class="btn primary" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>
            <a class="btn" href="{{ route('awards.badge', $award->certificate_code) }}" download="{{ $award->certificate_code }}.svg"><i class="bi bi-download"></i> Badge image</a>
            <button class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText(@json($verifyUrl)).then(function(){ var t=document.getElementById('toast'); t.classList.add('show'); setTimeout(function(){ t.classList.remove('show'); }, 1800); });"><i class="bi bi-link-45deg"></i> Copy link</button>
            <a class="btn" target="_blank" rel="noopener" href="https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name={{ urlencode($award->award_name) }}&organizationName={{ urlencode('HIMS Hospital') }}&issueYear={{ \Carbon\Carbon::parse($award->awarded_on)->year }}&issueMonth={{ \Carbon\Carbon::parse($award->awarded_on)->month }}&certUrl={{ urlencode($verifyUrl) }}&certId={{ urlencode($award->certificate_code) }}"><i class="bi bi-linkedin"></i> Add to LinkedIn</a>
            <a class="btn" href="mailto:?subject={{ rawurlencode($award->award_name.' certificate') }}&body={{ rawurlencode('View and verify the certificate: '.$verifyUrl) }}"><i class="bi bi-envelope"></i> Email</a>
        </div>
    </div>

    <main class="cert">
        <span class="corner tl"></span><span class="corner br"></span>
        @if($award->status !== 'issued')<div class="revoked-stamp">REVOKED</div>@endif

        <div class="org">Hospital Information &amp; Management System</div>
        <div class="org-sub">Human Resources · Performance &amp; Development (HR3)</div>

        <div class="title">{{ $award->certificate_title }}</div>
        <div class="presented">This certificate is proudly presented to</div>
        <div class="name">{{ $award->first_name }} {{ $award->last_name }}</div>
        <div class="role">{{ $award->position_title ?: 'Hospital staff' }}{{ $award->department_name ? ' · '.$award->department_name : '' }}</div>

        <div class="award"><span class="seal"><i class="bi {{ $award->badge_icon }}"></i></span> {{ $award->award_name }}{{ $award->period_label ? ' — '.$award->period_label : '' }}</div>
        <p class="body">{{ $award->body_text }}</p>

        <div class="foot">
            <div class="sign">
                <div class="line"></div>
                <div class="who">{{ $award->signatory_name ?: ($award->awarded_by_name ?: 'Human Resources') }}</div>
                <div class="what">{{ $award->signatory_title ?: 'Human Resources' }}</div>
            </div>
            <div class="code">
                Given {{ \Carbon\Carbon::parse($award->awarded_on)->format('F j, Y') }}
                <strong>{{ $award->certificate_code }}</strong>
                Verify at {{ preg_replace('#^https?://#', '', $verifyUrl) }}
            </div>
            <div class="sign">
                <div class="line"></div>
                <div class="who">{{ $award->awarded_by_name ?: 'Recognition Committee' }}</div>
                <div class="what">{{ $award->source === 'auto' ? 'Awarded automatically by HR3' : 'Awarded by' }}</div>
            </div>
        </div>
    </main>
    <div class="toast" id="toast">Link copied</div>
</body>
</html>
