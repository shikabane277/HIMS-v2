@php
    // Downloadable badge image. Plain SVG (no external fonts or icons) so it
    // renders the same in an email, a slide or a LinkedIn post.
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $award->badge_color) ? $award->badge_color : '#16a34a';
    $name = \Illuminate\Support\Str::limit($award->award_name, 34);
    $person = \Illuminate\Support\Str::limit(trim($award->first_name.' '.$award->last_name), 30);
    $date = \Carbon\Carbon::parse($award->awarded_on)->format('M Y');
    $revoked = $award->status !== 'issued';
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="460" viewBox="0 0 400 460" role="img" aria-label="{{ $name }} badge for {{ $person }}">
  <defs>
    <radialGradient id="g" cx="35%" cy="30%" r="75%">
      <stop offset="0" stop-color="#ffffff" stop-opacity=".55"/>
      <stop offset=".45" stop-color="{{ $c }}" stop-opacity=".95"/>
      <stop offset="1" stop-color="{{ $c }}"/>
    </radialGradient>
  </defs>
  <rect width="400" height="460" rx="28" fill="#ffffff"/>
  <path d="M150 250 L120 380 L165 355 L190 395 L200 260 Z" fill="{{ $c }}" opacity=".75"/>
  <path d="M250 250 L280 380 L235 355 L210 395 L200 260 Z" fill="{{ $c }}" opacity=".55"/>
  <circle cx="200" cy="170" r="120" fill="{{ $c }}" opacity=".18"/>
  <circle cx="200" cy="170" r="104" fill="url(#g)"/>
  <circle cx="200" cy="170" r="88" fill="none" stroke="#ffffff" stroke-width="3" stroke-dasharray="4 6" opacity=".8"/>
  <path d="M200 108 l17 36 40 5 -29 27 8 39 -36-19 -36 19 8-39 -29-27 40-5z" fill="#ffffff"/>
  <text x="200" y="300" text-anchor="middle" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-size="22" font-weight="700" fill="#0f172a">{{ $name }}</text>
  <text x="200" y="328" text-anchor="middle" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-size="16" fill="#334155">{{ $person }}</text>
  <text x="200" y="420" text-anchor="middle" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-size="12" fill="#64748b">HIMS HR3 · {{ $date }} · {{ $award->certificate_code }}</text>
  @if($revoked)
  <text x="200" y="190" text-anchor="middle" font-family="Georgia, serif" font-size="44" font-weight="700" fill="#dc2626" transform="rotate(-18 200 190)">REVOKED</text>
  @endif
</svg>
