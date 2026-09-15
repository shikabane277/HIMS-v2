@push('modals')
<div class="hims-modal-backdrop" id="auditHistoryModal" style="display:none">
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

            if (!res.data || res.data.length === 0) {
                content.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:40px">No changes recorded yet.</div>';
                return;
            }

            let html = '<div style="display:flex;flex-direction:column;gap:12px">';
            res.data.forEach(item => {
                let actionLabel = item.action.replace('_', ' ').toUpperCase();
                let dateStr = new Date(item.timestamp).toLocaleString();
                let actorStr = item.actor_name || 'System';

                let before = item.before_state ? JSON.parse(item.before_state) : null;
                let after = item.after_state ? JSON.parse(item.after_state) : null;

                html += `
                    <div style="border:1px solid var(--hims-border);border-radius:8px;padding:12px;background:#fff">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <span class="hims-badge blue" style="font-size:11px">${actionLabel}</span>
                            <span style="font-size:11.5px;color:#6b7280">${dateStr}</span>
                        </div>
                        <div style="font-size:12.5px;font-weight:600;margin-bottom:6px">By: ${actorStr}</div>
                `;

                if (before || after) {
                    // `overflow-wrap:anywhere` is load-bearing, not cosmetic. A
                    // JSON.stringify dump has no spaces in it, so it is one
                    // unbreakable token — 714px of it inside a 198px box on a
                    // 320px phone. And `overflow-y:auto` alone does not leave
                    // overflow-x at `visible`: CSS computes the other axis to
                    // `auto` as soon as one axis is not visible, so the box
                    // silently became the sideways swipe the mobile rules exist
                    // to remove, hiding the changed values themselves. Wrapping
                    // it puts them under the max-height's vertical scroll, which
                    // is the direction a reader can find.
                    html += '<div style="font-size:12px;background:#f8fafc;padding:8px;border-radius:6px;font-family:monospace;max-height:140px;overflow-y:auto;overflow-wrap:anywhere">';
                    if (after) html += `<div><strong>After:</strong> ${JSON.stringify(after)}</div>`;
                    if (before) html += `<div style="color:#6b7280;margin-top:4px"><strong>Before:</strong> ${JSON.stringify(before)}</div>`;
                    html += '</div>';
                }

                html += '</div>';
            });
            html += '</div>';
            content.innerHTML = html;
        })
        .catch(err => {
            loading.style.display = 'none';
            content.style.display = 'block';
            content.innerHTML = '<div style="color:#dc2626;padding:20px;text-align:center">Failed to load change history.</div>';
        });
});
</script>
@endpush
