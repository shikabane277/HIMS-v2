{{--
    New-review-cycle form, as a modal. Replaces the standalone create page; the
    route, the POST and the validation are unchanged.

    Opened by any [data-modal-open="cycleCreateModal"] trigger, and also on load
    when ?new=cycle is in the query string — that is how the dashboard's "New
    Review Cycle" quick action stays one click after losing its own page.

    Validation errors cannot be shown inside the modal, because storeCycle()
    redirects back on failure; the hidden _modal field flashes through old() so
    the page knows which of its modals to reopen with the user's input intact.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="cycleCreateModal" role="dialog" aria-modal="true" aria-labelledby="cycleCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="cycleCreateTitle"><i class="bi bi-calendar-plus"></i> New Review Cycle</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('performance.cycles.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="cycleCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'cycleCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="cc_cycle_name">Cycle Name *</label>
                        <input type="text" name="cycle_name" id="cc_cycle_name" class="hims-input" required
                               value="{{ old('cycle_name') }}" placeholder="e.g. 2026 Annual Performance Review">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cc_cycle_type">Cycle Type *</label>
                        <select name="cycle_type" id="cc_cycle_type" class="hims-input hims-select" required>
                            <option value="">— Select —</option>
                            <option value="annual"       @selected(old('cycle_type')==='annual')>Annual</option>
                            <option value="semi_annual"  @selected(old('cycle_type')==='semi_annual')>Semi-Annual</option>
                            <option value="quarterly"    @selected(old('cycle_type')==='quarterly')>Quarterly</option>
                            <option value="probationary" @selected(old('cycle_type')==='probationary')>Probationary</option>
                        </select>
                        <div style="font-size:11.5px;color:#9ca3af;margin:5px 0 8px">
                            Picking a type fills the dates in — adjust them if you need to.
                        </div>
                    </div>

                    <div class="col-md-6"></div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cc_start_date">Start Date *</label>
                        <input type="date" name="start_date" id="cc_start_date" class="hims-input" required value="{{ old('start_date') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="cc_end_date">End Date *</label>
                        <input type="date" name="end_date" id="cc_end_date" class="hims-input" required value="{{ old('end_date') }}">
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Cycle</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
{{-- Defines window.himsCycleRange, used by the type-change handler below. --}}
@include('performance.cycles._date-range-js')
@push('scripts')
<script>
(function () {
    const typeEl  = document.getElementById('cc_cycle_type');
    const startEl = document.getElementById('cc_start_date');
    const endEl   = document.getElementById('cc_end_date');

    typeEl.addEventListener('change', function () {
        const range = window.himsCycleRange(this.value);
        if (!range) return;
        startEl.value = range[0];
        endEl.value   = range[1];
    });

    @if(request('new') === 'cycle')
        window.himsModal.open('cycleCreateModal');
    @elseif($errors->any())
        if (@json(old('_modal')) === 'cycleCreateModal') window.himsModal.open('cycleCreateModal');
    @endif
})();
</script>
@endpush
