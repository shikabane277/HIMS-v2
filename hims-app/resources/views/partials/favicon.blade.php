{{--
    Browser / tab icon.

    Included by every standalone document (the seven Blade files that carry their
    own <!DOCTYPE>), so the tab icon cannot drift between the app shell, the auth
    pages and the Breeze layouts.

    The icon itself is public/favicon.ico.

    The ?v= hash is load-bearing, not decoration. Browsers cache favicons far more
    aggressively than page assets — Chrome keeps them in a separate Favicons store
    that a hard reload does not clear, and this file previously shipped as a
    0-byte placeholder, so any client that saw the empty version cached "this site
    has no icon" against the bare URL. Keying the URL to the file's own content
    means every swap of favicon.ico is a new URL and re-fetches everywhere,
    without anyone having to remember to bump a version by hand.

    No `sizes` attribute on purpose — the browser reads the real entry table out of
    the .ico, so the markup stays correct whatever sizes that file contains. No SVG
    variant either: Chrome prefers an SVG link over the .ico and would silently
    shadow it.
--}}
@php
    // filemtime/md5_file are cheap and cached by the OS; guard for a missing file
    // so a fresh checkout without the asset renders rather than throwing.
    $himsIcon = public_path('favicon.ico');
    $himsIconV = is_file($himsIcon) ? substr(md5_file($himsIcon), 0, 8) : null;
@endphp
<link rel="icon" href="{{ asset('favicon.ico').($himsIconV ? '?v='.$himsIconV : '') }}">
