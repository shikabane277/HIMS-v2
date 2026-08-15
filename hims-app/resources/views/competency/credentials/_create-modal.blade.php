{{--
    Add-credential form, as a modal. Replaces the standalone create page; the
    route, the POST and the validation are unchanged.

    Included on both the Competency overview and the Credentials register, since
    both carry an "Add Credential" button. Whichever page submitted is the one
    Laravel redirects back to on a validation failure, so the hidden _modal
    field is enough to reopen the right modal on the right page.

    Needs $employees from the host controller.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="credentialCreateModal" role="dialog" aria-modal="true" aria-labelledby="credentialCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="credentialCreateTitle"><i class="bi bi-patch-plus-fill"></i> Add Credential / License</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('competency.credentials.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="credentialCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'credentialCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="crc_employee_id">Employee *</label>
                        <select name="employee_id" id="crc_employee_id" class="hims-input hims-select" required>
                            <option value="">— Select Employee —</option>
                            @foreach($employees ?? [] as $emp)
                                <option value="{{ $emp->employee_id }}" @selected(old('employee_id') === $emp->employee_id)>
                                    {{ $emp->first_name }} {{ $emp->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="crc_credential_type">Credential Type *</label>
                        <select name="credential_type" id="crc_credential_type" class="hims-input hims-select" required>
                            <option value="">— Select Type —</option>
                            @foreach(['PRC License','BLS Certification','ACLS Certification','IV Therapy','Board Certificate','JCI Training','Other'] as $type)
                                <option value="{{ $type }}" @selected(old('credential_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="crc_credential_number">License/Certificate Number</label>
                        <input type="text" name="credential_number" id="crc_credential_number" class="hims-input"
                               value="{{ old('credential_number') }}" placeholder="e.g. 0123456">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="crc_issuing_body">Issuing Body</label>
                        <input type="text" name="issuing_body" id="crc_issuing_body" class="hims-input"
                               value="{{ old('issuing_body') }}" placeholder="e.g. PRC, AHA">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="crc_issue_date">Issue Date</label>
                        <input type="date" name="issue_date" id="crc_issue_date" class="hims-input" value="{{ old('issue_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="crc_expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="crc_expiry_date" class="hims-input" value="{{ old('expiry_date') }}">
                        <div style="font-size:11.5px;color:#9ca3af;margin-top:5px">
                            Leave blank for a credential that does not lapse. Anything expiring within 30 days is flagged.
                        </div>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Add Credential</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
@if(request('new') === 'credential' || ($errors->any() && old('_modal') === 'credentialCreateModal'))
@push('scripts')
<script>window.himsModal.open('credentialCreateModal');</script>
@endpush
@endif
