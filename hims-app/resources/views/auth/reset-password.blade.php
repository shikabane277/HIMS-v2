<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — HIMS Performance & Development</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/hims.css') }}">
    <style>
        .auth-bg-circles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
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
            <p>Choose a New Password</p>
        </div>

        @if($errors->any())
            <div class="hims-alert error"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="form-group">
                <label class="hims-label" for="email"><i class="bi bi-envelope"></i> Email Address</label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope icon"></i>
                    <input type="email" id="email" name="email" class="hims-input" placeholder="you@hospital.ph"
                           value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label class="hims-label" for="password"><i class="bi bi-lock"></i> New Password</label>
                <div class="input-with-icon">
                    <i class="bi bi-lock icon"></i>
                    <input type="password" id="password" name="password" class="hims-input"
                           placeholder="At least 8 characters" required autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('password','pwEye')"><i class="bi bi-eye" id="pwEye"></i></button>
                </div>
            </div>

            <div class="form-group">
                <label class="hims-label" for="password_confirmation"><i class="bi bi-lock-fill"></i> Confirm New Password</label>
                <div class="input-with-icon">
                    <i class="bi bi-lock-fill icon"></i>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="hims-input"
                           placeholder="Re-enter your new password" required autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('password_confirmation','pwEye2')"><i class="bi bi-eye" id="pwEye2"></i></button>
                </div>
            </div>

            <button type="submit" class="btn-hims btn-hims-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
                <i class="bi bi-shield-check"></i> Reset Password
            </button>
        </form>

        <div style="text-align:center;margin-top:16px">
            <a href="{{ route('login') }}" style="font-size:13px;color:var(--hims-primary);text-decoration:none;font-weight:500">
                <i class="bi bi-arrow-left"></i> Back to Sign In
            </a>
        </div>
    </div>
</div>
<script>
function togglePw(fieldId, eyeId) {
    const pw = document.getElementById(fieldId);
    const eye = document.getElementById(eyeId);
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
