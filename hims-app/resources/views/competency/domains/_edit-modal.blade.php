@push('modals')
<div class="hims-modal-backdrop" id="domainEditModal">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5><i class="bi bi-pencil-square"></i> Edit Competency Domain</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <form method="POST" id="domainEditForm" action="">
            @csrf
            @method('PUT')
            <div class="hims-modal-body">
                <div class="mb-3">
                    <label class="hims-label">Domain Name *</label>
                    <input type="text" name="domain_name" id="edit_domain_name" class="hims-input" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="hims-label">Description</label>
                    <textarea name="description" id="edit_domain_description" class="hims-input" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="hims-label">Status</label>
                    <select name="is_active" id="edit_domain_is_active" class="hims-input hims-select">
                        <option value="1">Active</option>
                        <option value="0">Deactivated / Inactive</option>
                    </select>
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
    const btn = e.target.closest('[data-domain-edit]');
    if (!btn) return;
    const form = document.getElementById('domainEditForm');
    form.action = btn.dataset.action;
    document.getElementById('edit_domain_name').value = btn.dataset.name;
    document.getElementById('edit_domain_description').value = btn.dataset.desc || '';
    document.getElementById('edit_domain_is_active').value = btn.dataset.active !== undefined ? btn.dataset.active : '1';
});
</script>
@endpush
