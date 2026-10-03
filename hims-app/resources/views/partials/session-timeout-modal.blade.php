{{--
    HIMS Hospital Security: Inactivity Session Timeout Warning Modal
--}}
<div id="himsSessionTimeoutModal" class="hims-session-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center">
    <div class="hims-session-modal-card" style="background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);width:100%;max-width:440px;margin:16px;padding:24px;border:1px solid #e2e8f0;text-align:center;animation:fadeInUp .25s ease">
        <div style="width:52px;height:52px;border-radius:50%;background:#fef3c7;color:#d97706;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:16px">
            <i class="bi bi-clock-history"></i>
        </div>
        <h4 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 8px">Session Inactivity Warning</h4>
        <p style="font-size:14px;color:#64748b;line-height:1.5;margin:0 0 20px">
            For hospital security, your session will automatically expire in
            <strong id="sessionTimeoutCountdown" style="color:#d97706;font-size:16px">60</strong> seconds due to inactivity.
        </p>
        <div style="display:flex;gap:12px;justify-content:center">
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="btn-hims btn-hims-ghost" style="padding:10px 18px;font-size:14px">
                    <i class="bi bi-box-arrow-right"></i> Sign Out Now
                </button>
            </form>
            <button type="button" id="himsStaySignedInBtn" class="btn-hims btn-hims-primary" style="padding:10px 20px;font-size:14px">
                <i class="bi bi-shield-check"></i> Stay Signed In
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // 14 minutes idle before showing warning, 60 seconds warning countdown (total 15 mins)
    const IDLE_LIMIT_MS = 14 * 60 * 1000;
    const COUNTDOWN_SECONDS = 60;

    let idleTimer = null;
    let countdownInterval = null;
    let secondsLeft = COUNTDOWN_SECONDS;
    let isWarningShowing = false;
    let lastPing = Date.now();

    const modal = document.getElementById('himsSessionTimeoutModal');
    const countdownEl = document.getElementById('sessionTimeoutCountdown');
    const stayBtn = document.getElementById('himsStaySignedInBtn');

    function resetIdleTimer() {
        if (isWarningShowing) return;

        clearTimeout(idleTimer);

        // Throttle background session refresh ping (at most once every 5 minutes while active)
        if (Date.now() - lastPing > 5 * 60 * 1000) {
            pingSession();
        }

        idleTimer = setTimeout(showWarningModal, IDLE_LIMIT_MS);
    }

    function showWarningModal() {
        isWarningShowing = true;
        secondsLeft = COUNTDOWN_SECONDS;
        if (countdownEl) countdownEl.textContent = secondsLeft;
        if (modal) modal.style.display = 'flex';

        clearInterval(countdownInterval);
        countdownInterval = setInterval(function() {
            secondsLeft--;
            if (countdownEl) countdownEl.textContent = secondsLeft;

            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                window.location.href = "{{ route('login') }}?timeout=1";
            }
        }, 1000);
    }

    function pingSession() {
        lastPing = Date.now();
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token) return;

        fetch("{{ route('session.ping') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        }).catch(function() {});
    }

    if (stayBtn) {
        stayBtn.addEventListener('click', function() {
            pingSession();
            clearInterval(countdownInterval);
            if (modal) modal.style.display = 'none';
            isWarningShowing = false;
            resetIdleTimer();
        });
    }

    const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
    activityEvents.forEach(function(evt) {
        window.addEventListener(evt, resetIdleTimer, { passive: true });
    });

    resetIdleTimer();
})();
</script>
