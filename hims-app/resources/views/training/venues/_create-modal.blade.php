{{--
    Add a training venue, as a modal.

    Replaces training/venues/create.blade.php and its GET route. Needs nothing
    from the controller.

    Hosted on the Venues page, and reached from ?new=venue — which is what the
    "+" button on the Training index's Venues card now links to, since a button
    on one page cannot open a modal on another.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="venueCreateModal" role="dialog" aria-modal="true" aria-labelledby="venueCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="venueCreateTitle"><i class="bi bi-building-add"></i> Add Training Venue</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('training.venues.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="venueCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'venueCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                @php $isOld = $errors->any() && old('_modal') === 'venueCreateModal'; @endphp

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="tv_venue_name">Venue Name *</label>
                        <input type="text" name="venue_name" id="tv_venue_name" class="hims-input" required
                               value="{{ $isOld ? old('venue_name') : '' }}" placeholder="e.g. Training Center A">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="tv_building">Building</label>
                        <input type="text" name="building" id="tv_building" class="hims-input"
                               value="{{ $isOld ? old('building') : '' }}" placeholder="e.g. Main Building">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="tv_floor">Floor</label>
                        <input type="text" name="floor" id="tv_floor" class="hims-input"
                               value="{{ $isOld ? old('floor') : '' }}" placeholder="e.g. 3rd Floor">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="tv_capacity">Capacity *</label>
                        <input type="number" name="capacity" id="tv_capacity" class="hims-input" required min="1"
                               value="{{ $isOld ? old('capacity') : '' }}" placeholder="50">
                        <small style="color:#9ca3af;font-size:11.5px">A session cannot be booked past its venue's capacity.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="tv_equipment">Equipment</label>
                        <input type="text" name="equipment" id="tv_equipment" class="hims-input"
                               value="{{ $isOld ? old('equipment') : '' }}" placeholder="e.g. Projector, Whiteboard">
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Add Venue</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if(request('new') === 'venue' || ($errors->any() && old('_modal') === 'venueCreateModal'))
@push('scripts')
<script>window.himsModal.open('venueCreateModal');</script>
@endpush
@endif
