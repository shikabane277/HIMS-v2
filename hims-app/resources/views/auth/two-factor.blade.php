<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — HIMS Performance & Development</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('partials.app-css')
    <style>
        .auth-bg-circles {
            position: absolute; inset: 0; overflow: hidden; pointer-events: none;
        }
        .auth-bg-circles span {
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,.04); animation: floatUp 12s infinite ease-in-out;
        }
        .auth-bg-circles span:nth-child(1) { width:300px;height:300px;top:-80px;left:-80px;animation-delay:0s; }
        .auth-bg-circles span:nth-child(2) { width:200px;height:200px;bottom:60px;right:40px;animation-delay:3s; }
        .auth-bg-circles span:nth-child(3) { width:150px;height:150px;top:40%;left:30%;animation-delay:6s; }
        @keyframes floatUp {
            0%,100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }
        .form-group { margin-bottom: 20px; }
        .code-input {
            width: 100%;
            letter-spacing: 10px;
            font-size: 26px;
            text-align: center;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-weight: 700;
            padding: 14px 12px;
            border-radius: 8px;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            box-sizing: border-box;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            text-transform: none;
        }
        .code-input:focus {
            border-color: var(--hims-primary);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
            background: #ffffff;
        }
        .case-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
        }
        .case-notice i {
            color: #d97706;
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .dev-badge {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #065f46;
            word-break: break-all;
        }
        .divider { display:flex;align-items:center;gap:12px;margin:22px 0;color:#d1d5db;font-size:12px; }
        .divider::before,.divider::after { content:'';flex:1;height:1px;background:#e5e7eb; }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-bg-circles">
        <span></span><span></span><span></span>
    </div>

    <div class="auth-card" style="animation:fadeInUp .4s ease forwards">
        <div class="auth-logo">
            <div class="logo-icon" style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%)">
                <i class="bi bi-shield-lock-fill text-white" style="font-size:28px"></i>
            </div>
            <h1>Two-Factor Authentication</h1>
            <p>Identity Verification Required</p>
        </div>

        <p style="font-size:13px;color:var(--hims-gray);margin:0 0 16px;line-height:1.6;text-align:center">
            A 6-character verification code has been sent to<br>
            <strong style="color:var(--hims-dark)">{{ $maskedEmail }}</strong>
        </p>

        <div class="case-notice">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Case-Sensitive Verification</strong><br>
                The 6-character code must be entered with exact <strong>uppercase</strong> and <strong>lowercase</strong> characters.
            </div>
        </div>

        @if(session('status'))
            <div class="hims-alert success" data-auto-dismiss style="margin-bottom:16px">
                <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
            </div>
        @endif

        @if(session('dev_code_notice'))
            <div class="dev-badge">
                <i class="bi bi-info-circle-fill"></i> {{ session('dev_code_notice') }}
            </div>
        @endif

        @if($errors->any())
            <div class="hims-alert error" style="margin-bottom:16px">
                <i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf
            <div class="form-group">
                <label class="hims-label" for="code" style="text-align:center;display:block;margin-bottom:8px">
                    <i class="bi bi-key"></i> Enter 6-Character Code
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    class="code-input"
                    maxlength="6"
                    placeholder="••••••"
                    value="{{ old('code') }}"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    spellcheck="false"
                    autocapitalize="none"
                >
            </div>

            <button type="submit" class="btn-hims btn-hims-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
                <i class="bi bi-shield-check"></i> Verify & Sign In
            </button>
        </form>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:18px;font-size:13px">
            <form method="POST" action="{{ route('two-factor.resend') }}">
                @csrf
                <button type="submit" style="background:none;border:none;color:var(--hims-primary);cursor:pointer;font-size:13px;font-weight:600;padding:0;display:inline-flex;align-items:center;gap:4px">
                    <i class="bi bi-arrow-clockwise"></i> Resend Code
                </button>
            </form>

            <a href="{{ route('two-factor.cancel') }}" style="color:#6b7280;text-decoration:none;font-weight:500;display:inline-flex;align-items:center;gap:4px">
                <i class="bi bi-arrow-left"></i> Back to Sign In
            </a>
        </div>

        <div class="divider">Hospital Security Policy</div>

        <div style="text-align:center">
            <p style="font-size:12px;color:#94a3b8;margin:0">
                <i class="bi bi-shield-shaded" style="color:var(--hims-primary)"></i>
                Codes expire after 10 minutes · Attempt-limited protection
            </p>
        </div>
    </div>
</div>
</body>
</html>
