{{--
    New renewal rule, as a modal. Replaces the inline col-lg-5 form that used to
    sit beside the rules table and squeeze it into two-thirds of the page.

    Needs $credentialTypes from ComplianceController::rulesIndex() — the datalist
    behind the Subject Key field, which is what stops a credential rule being
    typed with a spelling no employee credential actually carries.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport rather than against the content
    wrapper's transform.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="ruleCreateModal" role="dialog" aria-modal="true" aria-labelledby="ruleCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="ruleCreateTitle"><i class="bi bi-plus-circle"></i> New Renewal Rule</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('learning.renewals.rules.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="ruleCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'ruleCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="rule_subject_type">Applies To *</label>
                        <select name="subject_type" id="rule_subject_type" class="hims-input hims-select" required>
                            <option value="credential" @selected(old('subject_type','credential') === 'credential')>A credential type</option>
                            <option value="cpd" @selected(old('subject_type') === 'cpd')>A CPD category</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="rule_subject_key">Subject Key *</label>
                        <input type="text" name="subject_key" id="rule_subject_key" class="hims-input" list="hims-credential-types"
                               value="{{ old('subject_key') }}" required maxlength="100"
                               placeholder="e.g. PRC Nursing License">
                        <datalist id="hims-credential-types">
                            @foreach($credentialTypes as $type)
                            <option value="{{ $type }}"></option>
                            @endforeach
                        </datalist>
                        <small style="color:#9ca3af;font-size:11.5px">
                            For a credential rule this must match the credential type exactly as recorded
                            on the employee's credential — the list suggests what is already in use.
                        </small>
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="rule_label">Label *</label>
                        <input type="text" name="label" id="rule_label" class="hims-input" value="{{ old('label') }}" required maxlength="150"
                               placeholder="e.g. PRC licence renewal (3-year CPD)">
                    </div>
                    <div class="col-6">
                        <label class="hims-label" for="rule_required_hours">Hours Required *</label>
                        <input type="number" name="required_hours" id="rule_required_hours" class="hims-input"
                               value="{{ old('required_hours', 45) }}" required min="0" max="9999" step="0.5">
                    </div>
                    <div class="col-6">
                        <label class="hims-label" for="rule_cycle_months">Cycle Length (months) *</label>
                        <input type="number" name="cycle_months" id="rule_cycle_months" class="hims-input"
                               value="{{ old('cycle_months', 36) }}" required min="1" max="120">
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="rule_grace_days">Grace Days</label>
                        <input type="number" name="grace_days" id="rule_grace_days" class="hims-input"
                               value="{{ old('grace_days', 0) }}" min="0" max="365">
                        <small style="color:#9ca3af;font-size:11.5px">
                            Days past the window in which late hours still count. Leave at 0 for a hard close.
                        </small>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Rule</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if(request('new') === 'rule' || ($errors->any() && old('_modal') === 'ruleCreateModal'))
@push('scripts')
<script>window.himsModal.open('ruleCreateModal');</script>
@endpush
@endif
