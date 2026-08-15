{{--
    Review-cycle editor, as a modal.

    Included once per page and re-targeted per row: clicking any button carrying
    data-cycle-edit rewrites this one form's action and fills its fields from the
    data-* attributes on that button, so a table of twenty cycles still ships one
    form rather than twenty. The route, the PUT method and the validation are
    unchanged from the standalone page this replaced.

    Validation errors cannot be shown inside the modal — updateCycle() redirects
    on failure and the page reloads with the modal closed — so the host page
    reopens it on load when $errors is non-empty, with old() repopulating.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="cycleModal" role="dialog" aria-modal="true" aria-labelledby="cycleModalTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="cycleModalTitle"><i class="bi bi-pencil-square"></i> Edit Review Cycle</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" id="cycleModalForm" action="">
            @csrf
            @method('PUT')
            {{-- Not read by the controller. It exists so a failed validation
                 round-trip can put the form back on the right cycle: the POST
                 body is flashed, so old() hands the action back on reload. --}}
            <input type="hidden" name="_cycle_action" id="cm_action" value="{{ old('_cycle_action') }}">
            <div class="hims-modal-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="cm_cycle_name">Cycle Name *</label>
                        <input type="text" name="cycle_name" id="cm_cycle_name" class="hims-input" required
                               value="{{ old('cycle_name') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cm_cycle_type">Cycle Type *</label>
                        <select name="cycle_type" id="cm_cycle_type" class="hims-input hims-select" required>
                            <option value="annual"       @selected(old('cycle_type')==='annual')>Annual</option>
                            <option value="semi_annual"  @selected(old('cycle_type')==='semi_annual')>Semi-Annual</option>
                            <option value="quarterly"    @selected(old('cycle_type')==='quarterly')>Quarterly</option>
                            <option value="probationary" @selected(old('cycle_type')==='probationary')>Probationary</option>
                        </select>
                        {{-- Bottom margin, unlike the create page's copy of this hint:
                             the modal is narrower, so both hints wrap to two lines and
                             would otherwise touch the date labels in the row beneath. --}}
                        <div style="font-size:11.5px;color:#9ca3af;margin:5px 0 8px">
                            Changing the type refills the dates — adjust them if you need to.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cm_status">Status *</label>
                        <select name="status" id="cm_status" class="hims-input hims-select" required>
                            <option value="planned"  @selected(old('status')==='planned')>Planned</option>
                            <option value="active"   @selected(old('status')==='active')>Active</option>
                            <option value="closed"   @selected(old('status')==='closed')>Closed</option>
                            <option value="archived" @selected(old('status')==='archived')>Archived</option>
                        </select>
                        <div style="font-size:11.5px;color:#9ca3af;margin:5px 0 8px">
                            Only planned and active cycles can take new reviews.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cm_start_date">Start Date *</label>
                        <input type="date" name="start_date" id="cm_start_date" class="hims-input" required
                               value="{{ old('start_date') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cm_end_date">End Date *</label>
                        <input type="date" name="end_date" id="cm_end_date" class="hims-input" required
                               value="{{ old('end_date') }}">
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endpush

{{-- Opening, closing, Escape and backdrop dismissal come from the shared
     controller; the only thing particular to this modal is the per-row prefill. --}}
@include('partials.modal-js')
{{-- Defines window.himsCycleRange, used by the type-change handler below.
     Included first so the helper is pushed onto the stack ahead of its caller. --}}
@include('performance.cycles._date-range-js')
@push('scripts')
<script>
(function () {
    const backdrop = document.getElementById('cycleModal');
    if (!backdrop) return;

    const form   = document.getElementById('cycleModalForm');
    const action = document.getElementById('cm_action');
    const fields = {
        cycle_name: document.getElementById('cm_cycle_name'),
        cycle_type: document.getElementById('cm_cycle_type'),
        status:     document.getElementById('cm_status'),
        start_date: document.getElementById('cm_start_date'),
        end_date:   document.getElementById('cm_end_date'),
    };

    // Every button carrying data-cycle-edit opens the one form, re-pointed.
    // Delegated off document so rows rendered later still work, and registered
    // after the shared controller so the modal is already open when we fill it.
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-cycle-edit]');
        if (!btn) return;
        form.action = btn.dataset.action;
        action.value = btn.dataset.action;
        Object.keys(fields).forEach(k => { fields[k].value = btn.dataset[k] || ''; });
    });

    // Picking a type refills the dates, same rules as the create modal. This
    // fires on user input only — filling the fields when the modal opens is a
    // programmatic .value assignment, which raises no change event, so an
    // existing cycle's stored dates survive being opened and cancelled.
    fields.cycle_type.addEventListener('change', function () {
        const range = window.himsCycleRange(this.value);
        if (!range) return;
        fields.start_date.value = range[0];
        fields.end_date.value   = range[1];
    });

    // A failed validation round-trip lands back here with the modal shut and
    // the user's input in old(); reopen so the errors are where they typed.
    @if($errors->any())
        if (action.value) { form.action = action.value; window.himsModal.open('cycleModal'); }
    @endif
})();
</script>
@endpush
