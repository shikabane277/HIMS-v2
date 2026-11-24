{{--
    Idle tab terminator. Replaces the old session-timeout warning modal.

    HOSPITAL POLICY: no warning, no countdown, no "Stay signed in". Two minutes
    (SESSION_IDLE_TIMEOUT=120) of no interaction and the tab ends itself and
    returns to the public landing page.

    This script is not the security boundary — app/Http/Middleware/
    EnforceSessionTimeout.php is, and it refuses the next request from an
    expired session regardless of what happens here. What this adds is speed: it
    ends an unused tab at the idle limit instead of leaving a live page
    sitting on screen until somebody touches it again. So it is allowed to be
    best-effort, and it is written that way — every step is guarded, and the
    worst case is a tab that looks alive until its next click, which the server
    then rejects.

    WHY IT LISTENS TO PASSIVE EVENTS ONLY

    The activity list has to mean "a person is using this tab". `mousemove`,
    `keydown`, `click`, `touchstart`, `scroll` and `wheel` all qualify. The one
    event deliberately absent is anything driven by a timer: the previous
    implementation pinged the server on a schedule while "active", which is how
    a forgotten tab stayed signed in. Nothing here runs on an interval except
    the deadline check itself. The heartbeat added on 2026-11-24 only fires
    when one of those input events happened since the previous beat, so it
    reports real use and cannot keep an abandoned tab alive.
--}}
<script>
(function () {
    'use strict';

    var IDLE_LIMIT_MS = {{ \App\Http\Middleware\EnforceSessionTimeout::idleTimeoutSeconds() }} * 1000;
    var HEARTBEAT_URL = @json(route('session.activity'));
    var HEARTBEAT_EVERY_MS = 60 * 1000;
    var lastBeat = Date.now();
    var beating = false;
    var LOGIN_URL = @json(route('landing'));

    var idleTimer = null;
    var lastActivity = Date.now();
    var ended = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.content : '';
    }

    /**
     * End the session for real, then leave. The POST is what actually
     * invalidates the server-side session; if it fails we still navigate, so a
     * network blip cannot leave someone staring at records on a locked tab.
     */
    function endSession() {
        if (ended) {
            return;
        }
        ended = true;

        var done = function () {
            window.location.replace(LOGIN_URL + '?timeout=1');
        };

        try {
            var body = new FormData();
            body.append('_token', csrfToken());

            fetch('{{ route('logout') }}', {
                method: 'POST',
                body: body,
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                credentials: 'same-origin',
                keepalive: true
            }).then(done).catch(done);
        } catch (e) {
            done();
        }
    }

    function touch() {
        lastActivity = Date.now();
    }

    /**
     * One repeating check against a monotonic-ish deadline. setInterval is
     * coarser than a re-armed setTimeout but it cannot be starved by a burst of
     * activity re-arming it forever.
     */
    /**
     * Tell the server about real input. Sent at most once a minute and only
     * when someone has typed, clicked, scrolled or touched since the last beat,
     * so a person writing a long review stays signed in, while an untouched tab
     * sends nothing and the server-side timer still ends it.
     */
    function heartbeat() {
        if (beating || ended) {
            return;
        }
        beating = true;
        lastBeat = Date.now();

        try {
            fetch(HEARTBEAT_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (res) {
                beating = false;
                if (res.status === 401 || res.status === 419) {
                    ended = true;
                    window.location.replace(LOGIN_URL + '?timeout=1');
                }
            }).catch(function () {
                beating = false;
            });
        } catch (e) {
            beating = false;
        }
    }

    function check() {
        if (ended) {
            return;
        }

        var now = Date.now();

        if (now - lastActivity >= IDLE_LIMIT_MS) {
            endSession();

            return;
        }

        if (lastActivity > lastBeat && now - lastBeat >= HEARTBEAT_EVERY_MS) {
            heartbeat();
        }
    }

    ['mousemove', 'mousedown', 'keydown', 'click', 'touchstart', 'wheel', 'scroll'].forEach(function (evt) {
        document.addEventListener(evt, touch, { passive: true });
    });

    // A tab restored from the background must be judged immediately, not left
    // sitting on a live page until the next tick.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            check();
        }
    });

    window.setInterval(check, 5000);

    // Expose nothing; the deadline is not something a page should be able to
    // extend, and there is no "stay signed in" affordance to build.
})();
</script>
