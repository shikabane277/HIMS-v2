@push('modals')
<div class="hims-modal-backdrop" id="venueEditModal" role="dialog" aria-modal="true" aria-labelledby="venueEditTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="venueEditTitle"><i class="bi bi-pencil-square"></i> Edit Training Venue</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" id="venueEditForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="ve_venue_name">Venue Name *</label>
                        <input type="text" name="venue_name" id="ve_venue_name" class="hims-input" required>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ve_building">Building</label>
                        <input type="text" name="building" id="ve_building" class="hims-input">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ve_floor">Floor</label>
                        <input type="text" name="floor" id="ve_floor" class="hims-input">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ve_capacity">Capacity *</label>
                        <input type="number" name="capacity" id="ve_capacity" class="hims-input" required min="1">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="ve_is_active">Status</label>
                        <select name="is_active" id="ve_is_active" class="hims-input hims-select">
                            <option value="1">Active</option>
                            <option value="0">Offline / Inactive</option>
                        </select>
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

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-venue-edit]');
    if (!btn) return;
    const form = document.getElementById('venueEditForm');
    form.action = btn.dataset.action;
    document.getElementById('ve_venue_name').value = btn.dataset.name || '';
    document.getElementById('ve_building').value = btn.dataset.building || '';
    document.getElementById('ve_floor').value = btn.dataset.floor || '';
    document.getElementById('ve_capacity').value = btn.dataset.capacity || '';
    document.getElementById('ve_is_active').value = btn.dataset.active || '1';
});
</script>
@endpush
