{{--
    The app stylesheet link, centralised for the same reason the favicon is: four
    separate documents load it — the app shell plus the three auth pages, which
    each carry their own <!DOCTYPE> — so the URL cannot drift between them.

    The ?v= hash is load-bearing, not decoration. public/css/hims.css is served
    straight out of public/ with no build step, so unlike a Vite bundle its name
    never changes when its contents do. The deployed origin sends
    `Cache-Control: max-age=14400` and sits behind Cloudflare, which means a
    returning client — and the shared edge cache in front of every client — keeps
    the copy it already has for four hours after a release, while the HTML it
    styles is served fresh from the new container.

    That combination breaks a release the moment it adds a class, and it breaks it
    in the least obvious way: the page is not unstyled, only the *new* parts are.
    v2.5.0-beta.3 shipped .hims-tabs, .hims-modal, .hims-checklist and #ai-rail in
    one commit, so on deploy Learning's and Recognition's tab strips rendered as
    bare underlined links, and the AI rail — with no position:fixed to take it out
    of flow — laid out at the foot of the document, where openRail()'s final
    input.focus() scrolled the page to the bottom instead of opening a panel.

    Keying the URL to the file's own content means a changed stylesheet is a new
    URL, so it re-fetches past both caches immediately, with no version number for
    anyone to remember to bump. Same guard as the favicon: a checkout that is
    missing the file renders rather than throwing.
--}}
@php
    // md5_file is cheap and the read is OS-cached; guard for a missing file so a
    // fresh checkout without the asset renders rather than throwing.
    $himsCss = public_path('css/hims.css');
    $himsCssV = is_file($himsCss) ? substr(md5_file($himsCss), 0, 8) : null;
@endphp
<link rel="stylesheet" href="{{ asset('css/hims.css').($himsCssV ? '?v='.$himsCssV : '') }}">
