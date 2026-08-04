<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — HIMS Performance & Development</title>
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
            <p>Password Recovery</p>
        </div>

        <p style="font-size:13px;color:var(--hims-gray);margin:0 0 18px;line-height:1.6">
            Forgot your password? Enter your account email and we'll send you a
            link to choose a new one.
        </p>

        @if(session('status'))
            <div class="hims-alert success"><i class="bi bi-check-circle-fill"></i> {{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="hims-alert error"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-group">
                <label class="hims-label" for="email"><i class="bi bi-envelope"></i> Email Address</label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope icon"></i>
                    <input type="email" id="email" name="email" class="hims-input" placeholder="you@hospital.ph" value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn-hims btn-hims-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
                <i class="bi bi-send"></i> Send Password Reset Link
            </button>
        </form>

        <div style="text-align:center;margin-top:16px">
            <a href="{{ route('login') }}" style="font-size:13px;color:var(--hims-primary);text-decoration:none;font-weight:500">
                <i class="bi bi-arrow-left"></i> Back to Sign In
            </a>
        </div>
    </div>
</div>
</body>
</html>
