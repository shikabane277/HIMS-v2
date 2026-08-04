{{--
    Browser / tab icon.

    Included by every standalone document (the seven Blade files that carry their
    own <!DOCTYPE>), so the tab icon cannot drift between the app shell, the auth
    pages and the Breeze layouts.

    The icon itself is public/favicon.ico. No `sizes` attribute on purpose — the
    browser reads the real entry table out of the .ico, so the markup stays correct
    no matter which sizes that file happens to contain. The explicit link is what
    makes Chrome use it on a nested route; browsers also probe /favicon.ico
    unprompted, so the icon still resolves anywhere this partial is missed.

    Only the tab icon is set here — deliberately no SVG variant, since Chrome
    prefers an SVG link over the .ico and would silently shadow it.
--}}
<link rel="icon" href="{{ asset('favicon.ico') }}">
