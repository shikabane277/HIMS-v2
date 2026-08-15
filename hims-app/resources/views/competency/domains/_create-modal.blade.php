{{--
    New competency domain, as a modal.

    Replaces competency/domains/create.blade.php and its GET route. The form
    needs nothing from the controller, so the only thing hosting it costs the
    Competency index is this include.

    Reached from the Competency page's own button and from ?new=domain.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="domainCreateModal" role="dialog" aria-modal="true" aria-labelledby="domainCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="domainCreateTitle"><i class="bi bi-diagram-2"></i> New Competency Domain</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('competency.domains.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="domainCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'domainCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <p style="font-size:13px;color:#6b7280;margin:0 0 18px">
                    A domain is the top level of the competency framework — for example
                    <em>Clinical Care</em> or <em>Patient Safety &amp; Quality</em>. Categories and
                    individual competencies sit underneath it.
                </p>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="cd_domain_name">Domain Name *</label>
                        <input type="text" name="domain_name" id="cd_domain_name" class="hims-input" required maxlength="100"
                               value="{{ old('_modal') === 'domainCreateModal' ? old('domain_name') : '' }}" placeholder="e.g. Clinical Care">
                        <small style="color:#9ca3af;font-size:11.5px">Must be unique across the framework.</small>
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="cd_description">Description</label>
                        <textarea name="description" id="cd_description" class="hims-input" rows="4"
                                  placeholder="What this domain covers and who it applies to…">{{ old('_modal') === 'domainCreateModal' ? old('description') : '' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Domain</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if(request('new') === 'domain' || ($errors->any() && old('_modal') === 'domainCreateModal'))
@push('scripts')
<script>window.himsModal.open('domainCreateModal');</script>
@endpush
@endif
