@push('modals')
<div class="hims-modal-backdrop" id="auditHistoryModal">
    <div class="hims-modal" style="max-width:640px">
        <div class="hims-modal-header">
            <h4><i class="bi bi-clock-history"></i> Change History</h4>
            <button type="button" class="hims-modal-close" data-modal-dismiss>&times;</button>
        </div>
        <div class="hims-modal-body" id="auditHistoryBody">
            <div style="text-align:center;padding:30px;color:#6b7280" id="auditHistoryLoading">
                <i class="bi bi-arrow-repeat spin" style="font-size:24px"></i>
                <div style="margin-top:8px">Loading history...</div>
            </div>
            <div id="auditHistoryContent" style="display:none"></div>
        </div>
        <div class="hims-modal-footer">
            <button type="button" class="btn-hims btn-hims-ghost" data-modal-dismiss>Close</button>
        </div>
    </div>
</div>
@endpush

@include('partials.modal-js')

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-history-resource-type]');
    if (!btn) return;

    const type = btn.getAttribute('data-history-resource-type');
    const id = btn.getAttribute('data-history-resource-id');

    const loading = document.getElementById('auditHistoryLoading');
    const content = document.getElementById('auditHistoryContent');
    loading.style.display = 'block';
    content.style.display = 'none';
    content.innerHTML = '';

    window.himsModal.open('auditHistoryModal');

    fetch(`/audit/history?resource_type=${encodeURIComponent(type)}&resource_id=${encodeURIComponent(id)}`)
        .then(res => res.json())
        .then(res => {
            loading.style.display = 'none';
            content.style.display = 'block';

            content.innerHTML = '';
            if (!res.data || res.data.length === 0) {
                const empty = document.createElement('div');
                empty.style.cssText = 'text-align:center;color:#9ca3af;padding:40px';
                empty.textContent = 'No changes recorded yet.';
                content.appendChild(empty);
                return;
            }

            const list = document.createElement('div');
            list.style.cssText = 'display:flex;flex-direction:column;gap:12px';

            res.data.forEach(item => {
                const card = document.createElement('div');
                card.style.cssText = 'border:1px solid var(--hims-border);border-radius:8px;padding:12px;background:#fff';

                const header = document.createElement('div');
                header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:6px';

                const badge = document.createElement('span');
                badge.className = 'hims-badge blue';
                badge.style.fontSize = '11px';
                badge.textContent = (item.action || '').replace(/_/g, ' ').toUpperCase();

                const time = document.createElement('span');
                time.style.cssText = 'font-size:11.5px;color:#6b7280';
                time.textContent = item.timestamp ? new Date(item.timestamp).toLocaleString() : '';

                header.appendChild(badge);
                header.appendChild(time);
                card.appendChild(header);

                const actor = document.createElement('div');
                actor.style.cssText = 'font-size:12.5px;font-weight:600;margin-bottom:6px';
                actor.textContent = 'By: ' + (item.actor_name || 'System');
                card.appendChild(actor);

                let before = null;
                let after = null;
                try { if (item.before_state) before = JSON.parse(item.before_state); } catch (_) {}
                try { if (item.after_state) after = JSON.parse(item.after_state); } catch (_) {}

                if (before || after) {
                    const diffBox = document.createElement('div');
                    diffBox.style.cssText = 'font-size:12px;background:#f8fafc;padding:8px;border-radius:6px;font-family:monospace;max-height:140px;overflow-y:auto;overflow-wrap:anywhere';

                    if (after) {
                        const afterDiv = document.createElement('div');
                        const strong = document.createElement('strong');
                        strong.textContent = 'After: ';
                        afterDiv.appendChild(strong);
                        afterDiv.appendChild(document.createTextNode(JSON.stringify(after)));
                        diffBox.appendChild(afterDiv);
                    }

                    if (before) {
                        const beforeDiv = document.createElement('div');
                        beforeDiv.style.cssText = 'color:#6b7280;margin-top:4px';
                        const strong = document.createElement('strong');
                        strong.textContent = 'Before: ';
                        beforeDiv.appendChild(strong);
                        beforeDiv.appendChild(document.createTextNode(JSON.stringify(before)));
                        diffBox.appendChild(beforeDiv);
                    }

                    card.appendChild(diffBox);
                }

                list.appendChild(card);
            });

            content.appendChild(list);
        })
        .catch(err => {
            loading.style.display = 'none';
            content.style.display = 'block';
            content.innerHTML = '<div style="color:#dc2626;padding:20px;text-align:center">Failed to load change history.</div>';
        });
});
</script>
@endpush
