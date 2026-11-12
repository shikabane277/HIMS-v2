@php
    $consentTimestamp = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            $consentTimestamp = \Illuminate\Support\Facades\DB::table('system_settings')
                ->where('key', 'gdpr_consent_user_' . $user->id)
                ->value('value');
        }
    } catch (\Throwable) {
    }
    $consentTimestamp = $consentTimestamp ?: session('gdpr_consent_acknowledged_at');
@endphp

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('GDPR & Data Privacy Rights (EU GDPR / RA 10173)') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Exercise your statutory rights under the EU General Data Protection Regulation (GDPR Articles 13, 15, 17 & 20) and the Philippine Data Privacy Act of 2012 (RA 10173).') }}
        </p>
    </header>

    @if (session('status') === 'gdpr-consent-recorded')
        <div class="p-3 rounded-md bg-green-50 border border-green-200 text-sm text-green-800">
            {{ __('Your Data Processing & Privacy Notice acknowledgment has been recorded in the security audit log.') }}
        </div>
    @endif

    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200 space-y-3 text-sm text-gray-700">
        <div class="font-semibold text-gray-900">
            {{ __('Data Protection & Processing Summary (Articles 13 & 14)') }}
        </div>
        <ul class="list-disc pl-5 space-y-1 text-xs text-gray-600">
            <li><strong>Lawful Basis:</strong> Employment contract performance (Art. 6(1)(b)), healthcare regulatory compliance (Art. 6(1)(c)), and hospital security audit obligations.</li>
            <li><strong>Strict AI Prohibition (Art. 22 & Art. 25):</strong> The AI Assistant is strictly prohibited from accessing, querying, or transmitting any employee records or personal identifiable information (PII).</li>
            <li><strong>Privacy by Default (Art. 25 & Art. 32):</strong> Employee profiles, evaluations, and compliance tables are encrypted in transit, masked on screen, and locked behind the Record Access Password.</li>
            <li><strong>Storage Limitation (Art. 5(1)(e)):</strong> Expired OTPs, reset tokens, AI chat history, and aged audit logs are automatically purged on a daily retention schedule.</li>
        </ul>

        <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200">
            <div class="text-xs text-gray-600">
                @if($consentTimestamp)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Consent Acknowledged: {{ \Illuminate\Support\Carbon::parse($consentTimestamp)->format('M d, Y H:i') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        Pending explicit privacy notice acknowledgment
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('profile.gdpr-consent') }}">
                @csrf
                <x-secondary-button type="submit">
                    {{ $consentTimestamp ? __('Re-acknowledge Privacy Notice') : __('Acknowledge Privacy Notice (Art. 7)') }}
                </x-secondary-button>
            </form>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <a href="{{ route('profile.export-csv') }}"
           class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            {{ __('Download My Details as CSV (Spreadsheet / ZIP)') }}
        </a>
        <a href="{{ route('profile.export-data') }}"
           class="inline-flex items-center px-4 py-2 bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-800 focus:bg-gray-800 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('Download My Data (JSON — Art. 15 & 20)') }}
        </a>
    </div>
</section>

