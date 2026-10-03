@push('modals')
<div class="hims-modal-backdrop" id="hrIntegrationModal" role="dialog" aria-modal="true" aria-labelledby="hrIntegrationTitle">
    <div class="hims-modal" style="max-width:750px">
        <div class="hims-modal-header">
            <h5 id="hrIntegrationTitle"><i class="bi bi-hdd-network"></i> External HR Systems Integration</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <div class="hims-modal-body">
            <div class="p-3 mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px">
                <div class="d-flex align-items-center gap-2 mb-1" style="color:#166534;font-weight:600;font-size:13.5px">
                    <i class="bi bi-shield-check" style="font-size:16px"></i> Automated Feeds Active
                </div>
                <p style="margin:0;font-size:12.5px;color:#15803d;line-height:1.5">
                    Manual inputting in Competency is disabled. <strong>HR1</strong> supplies verified staff licenses & credentials. <strong>HR2</strong> supplies the competency framework and proficiency assessments. Configure API connection settings below or define them in your <code>.env</code> file.
                </p>
            </div>

            <div class="d-flex flex-column gap-4">
                {{-- HR1 System Card --}}
                @php
                    $hr1 = ($integrations['hr1'] ?? null) ?: (object)[
                        'integration_id' => '',
                        'system_name' => 'HR1 - Employee Master & Clinical Credentials',
                        'base_url' => config('services.hr1.base_url', ''),
                        'api_key' => config('services.hr1.api_key', ''),
                        'timeout' => config('services.hr1.timeout', 30),
                        'is_active' => true,
                        'last_synced_at' => null,
                        'last_sync_status' => 'idle',
                        'last_sync_message' => 'Configured via environment variables',
                        'synced_records_count' => 0,
                    ];
                @endphp
                <div class="p-3" style="border:1px solid var(--hims-border);border-radius:8px;background:var(--hims-surface)">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong style="font-size:14px;color:var(--hims-text)">HR1: Credentials & Licensure</strong>
                            <div style="font-size:11.5px;color:#6b7280">Pulls professional PRC licenses, board certificates, and expiration dates.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="hims-badge {{ ($hr1->last_sync_status ?? '') === 'success' ? 'green' : (($hr1->last_sync_status ?? '') === 'failed' ? 'red' : 'yellow') }}">
                                {{ ucfirst($hr1->last_sync_status ?? 'Idle') }}
                            </span>
                        </div>
                    </div>

                    <div style="font-size:11.5px;color:#6b7280;margin-bottom:12px">
                        Last Synced: <strong>{{ $hr1->last_synced_at ? \Carbon\Carbon::parse($hr1->last_synced_at)->format('M d, Y h:i A') : 'Never' }}</strong>
                        @if(!empty($hr1->last_sync_message))
                            &bull; <em>{{ $hr1->last_sync_message }}</em>
                        @endif
                    </div>

                    @if(!empty($hr1->integration_id))
                    <form method="POST" action="{{ route('competency.integrations.update', $hr1->integration_id) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="hims-label" style="font-size:11.5px">API Base URL</label>
                                <input type="url" name="base_url" class="hims-input" style="padding:6px 10px;font-size:12.5px"
                                       value="{{ $hr1->base_url }}" placeholder="https://hr1.hospital.internal/api/v1">
                            </div>
                            <div class="col-md-5">
                                <label class="hims-label" style="font-size:11.5px">API Key / Bearer Token</label>
                                <input type="password" name="api_key" class="hims-input" style="padding:6px 10px;font-size:12.5px"
                                       value="{{ $hr1->api_key }}" placeholder="Enter token or key">
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                                <label class="d-flex align-items-center gap-2" style="font-size:12px;cursor:pointer">
                                    <input type="checkbox" name="is_active" value="1" @checked($hr1->is_active)> Enable HR1 Feed
                                </label>
                                <button type="submit" class="btn-hims btn-hims-outline btn-sm">Save HR1 Settings</button>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>

                {{-- HR2 System Card --}}
                @php
                    $hr2 = ($integrations['hr2'] ?? null) ?: (object)[
                        'integration_id' => '',
                        'system_name' => 'HR2 - Competency & Talent Development System',
                        'base_url' => config('services.hr2.base_url', ''),
                        'api_key' => config('services.hr2.api_key', ''),
                        'timeout' => config('services.hr2.timeout', 30),
                        'is_active' => true,
                        'last_synced_at' => null,
                        'last_sync_status' => 'idle',
                        'last_sync_message' => 'Configured via environment variables',
                        'synced_records_count' => 0,
                    ];
                @endphp
                <div class="p-3" style="border:1px solid var(--hims-border);border-radius:8px;background:var(--hims-surface)">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong style="font-size:14px;color:var(--hims-text)">HR2: Competency Framework & Assessments</strong>
                            <div style="font-size:11.5px;color:#6b7280">Pulls domains, competencies dictionary, required levels, and staff proficiency evaluations.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="hims-badge {{ ($hr2->last_sync_status ?? '') === 'success' ? 'green' : (($hr2->last_sync_status ?? '') === 'failed' ? 'red' : 'yellow') }}">
                                {{ ucfirst($hr2->last_sync_status ?? 'Idle') }}
                            </span>
                        </div>
                    </div>

                    <div style="font-size:11.5px;color:#6b7280;margin-bottom:12px">
                        Last Synced: <strong>{{ $hr2->last_synced_at ? \Carbon\Carbon::parse($hr2->last_synced_at)->format('M d, Y h:i A') : 'Never' }}</strong>
                        @if(!empty($hr2->last_sync_message))
                            &bull; <em>{{ $hr2->last_sync_message }}</em>
                        @endif
                    </div>

                    @if(!empty($hr2->integration_id))
                    <form method="POST" action="{{ route('competency.integrations.update', $hr2->integration_id) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="hims-label" style="font-size:11.5px">API Base URL</label>
                                <input type="url" name="base_url" class="hims-input" style="padding:6px 10px;font-size:12.5px"
                                       value="{{ $hr2->base_url }}" placeholder="https://hr2.hospital.internal/api/v1">
                            </div>
                            <div class="col-md-5">
                                <label class="hims-label" style="font-size:11.5px">API Key / Bearer Token</label>
                                <input type="password" name="api_key" class="hims-input" style="padding:6px 10px;font-size:12.5px"
                                       value="{{ $hr2->api_key }}" placeholder="Enter token or key">
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                                <label class="d-flex align-items-center gap-2" style="font-size:12px;cursor:pointer">
                                    <input type="checkbox" name="is_active" value="1" @checked($hr2->is_active)> Enable HR2 Feed
                                </label>
                                <button type="submit" class="btn-hims btn-hims-outline btn-sm">Save HR2 Settings</button>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="hims-modal-footer d-flex justify-content-between align-items-center">
            <span style="font-size:12px;color:#6b7280">
                <i class="bi bi-clock-history"></i> Automated background sync runs hourly via scheduled task.
            </span>
            <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Close</button>
        </div>
    </div>
</div>
@endpush
