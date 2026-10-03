{{--
    HIMS Client-Side Security Protection:
    Prevents unauthorized DevTools opening, F12 inspect, source view, and right-click inspection.
--}}
<script>
(function() {
    'use strict';

    // 1. Prevent Right-Click Context Menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    }, true);

    // 2. Prevent Developer Tools and Source Inspection Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        // F12 key
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        const isModifier = e.ctrlKey || e.metaKey;

        if (isModifier) {
            const key = (e.key || '').toUpperCase();
            const keyCode = e.keyCode;

            // Ctrl+Shift+I (Inspect), Ctrl+Shift+J (Console), Ctrl+Shift+C (Inspect Element)
            if (e.shiftKey && (key === 'I' || key === 'J' || key === 'C' || keyCode === 73 || keyCode === 74 || keyCode === 67)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Ctrl+U (View Source)
            if (key === 'U' || keyCode === 85) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Ctrl+S (Save Page)
            if (key === 'S' || keyCode === 83) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }
    }, true);

    // 3. Clear and neutralise console inspection in production / hospital interface
    try {
        if (window.console) {
            const noop = function() {};
            window.console.log = noop;
            window.console.info = noop;
            window.console.debug = noop;
            window.console.table = noop;
            window.console.clear();
        }
    } catch (_) {}
})();
</script>
