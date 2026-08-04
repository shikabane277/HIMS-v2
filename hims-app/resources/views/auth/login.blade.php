<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HIMS Performance & Development</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/hims.css') }}">
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
        .form-group { margin-bottom: 18px; }
        .input-with-icon { position: relative; }
        .input-with-icon .icon { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--hims-primary);font-size:15px; }
        .input-with-icon .hims-input { padding-left: 38px; }
        .input-with-icon .toggle-pw { position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#9ca3af;font-size:15px;border:none;background:none; }
        .divider { display:flex;align-items:center;gap:12px;margin:20px 0;color:#d1d5db;font-size:12px; }
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
            <div class="logo-icon">🏥</div>
            <h1>HIMS</h1>
            <p>Performance & Development Module</p>
        </div>

        @if(session('status'))
            <div class="hims-alert success"><i class="bi bi-check-circle-fill"></i> {{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="hims-alert error"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label class="hims-label" for="email"><i class="bi bi-envelope"></i> Email Address</label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope icon"></i>
                    <input type="email" id="email" name="email" class="hims-input" placeholder="you@hospital.ph" value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                    <label class="hims-label" for="password" style="margin:0"><i class="bi bi-lock"></i> Password</label>
                    @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" style="font-size:12px;color:var(--hims-primary);text-decoration:none;font-weight:500">Forgot password?</a>
                    @endif
                </div>
                <div class="input-with-icon">
                    <i class="bi bi-lock icon"></i>
                    <input type="password" id="password" name="password" class="hims-input" placeholder="Enter your password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw()"><i class="bi bi-eye" id="pwEye"></i></button>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
                <input type="checkbox" id="remember" name="remember" style="width:16px;height:16px;accent-color:var(--hims-primary)">
                <label for="remember" style="font-size:13px;color:#6b7280;cursor:pointer">Keep me signed in</label>
            </div>

            <button type="submit" class="btn-hims btn-hims-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
                <i class="bi bi-box-arrow-in-right"></i> Sign In to HIMS
            </button>
        </form>

        <div class="divider">Secure Hospital System</div>

        <div style="text-align:center">
            <p style="font-size:12px;color:#9ca3af;margin:0">
                <i class="bi bi-shield-check" style="color:var(--hims-primary)"></i>
                Session-based authentication · AES-256 encrypted data · SHA-256 audit chain
            </p>
        </div>
    </div>
</div>
<script>
function togglePw() {
    const pw = document.getElementById('password');
    const eye = document.getElementById('pwEye');
    if (pw.type === 'password') {
        pw.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        eye.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
