{{--
    GDPR Articles 7, 13 & 14 / PH RA 10173 Data Privacy Notice & Consent Banner
--}}
@auth
@php
    $gdprAcknowledged = session('gdpr_consent_acknowledged_at');
    if (! $gdprAcknowledged) {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                $gdprAcknowledged = \Illuminate\Support\Facades\DB::table('system_settings')
                    ->where('key', 'gdpr_consent_user_' . auth()->id())
                    ->value('value');
                if ($gdprAcknowledged) {
                    session(['gdpr_consent_acknowledged_at' => $gdprAcknowledged]);
                }
            }
        } catch (\Throwable) {
        }
    }
@endphp

@if(! $gdprAcknowledged)
<div id="himsGdprBanner" role="region" aria-label="GDPR Data Privacy Notice"
     style="position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:1080;width:calc(100% - 32px);max-width:780px;background:#0f172a;color:#f8fafc;border:1px solid #334155;border-radius:12px;padding:14px 18px;box-shadow:0 16px 32px rgba(0,0,0,0.28);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:12.5px">
    <div style="flex:1;min-width:260px;line-height:1.5">
        <div style="font-weight:700;color:#34d399;margin-bottom:2px">
            <i class="bi bi-shield-check"></i> GDPR &amp; RA 10173 Data Protection Notice
        </div>
        <span style="color:#cbd5e1">
            HIMS processes workforce records strictly for hospital HR operations (GDPR Art. 6 &amp; Art. 32). The AI assistant has zero access to employee personal data. You may export or erase your data anytime under <a href="{{ route('profile.edit') }}" style="color:#6ee7b7;text-decoration:underline">Profile &amp; Privacy</a>.
        </span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
        <a href="{{ route('terms-of-service') }}" class="btn-hims btn-hims-ghost btn-sm" style="color:#e2e8f0;border:1px solid #475569;font-size:12px">
            Terms of Service
        </a>
        <a href="{{ route('profile.edit') }}" class="btn-hims btn-hims-ghost btn-sm" style="color:#e2e8f0;border:1px solid #475569;font-size:12px">
            Privacy Rights
        </a>
        <button type="button" id="himsGdprAckBtn" onclick="himsAcknowledgeGdpr()" class="btn-hims btn-hims-primary btn-sm" style="font-size:12px">
            <i class="bi bi-check2-circle"></i> Acknowledge
        </button>
    </div>
</div>
<script>
function himsAcknowledgeGdpr() {
    var banner = document.getElementById('himsGdprBanner');
    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var csrf = metaCsrf ? metaCsrf.content : '';
    fetch('{{ route('profile.gdpr-consent') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({})
    }).finally(function() {
        if (banner) banner.style.display = 'none';
    });
}
</script>
@endif
@endauth

