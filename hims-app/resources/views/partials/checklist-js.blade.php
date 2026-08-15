{{--
    Filter box for a .hims-checklist. Delegated off document and included once,
    the same way the modal controller is, so any number of checklists on a page
    share this one handler.

        <input data-checklist-filter="listId" …>
        <div class="hims-checklist" id="listId">
            <label>…</label>
            <div class="hims-checklist-empty" data-checklist-empty>…</div>
        </div>

    Filtering only hides rows — a box that scrolls out of view keeps its checked
    state, so typing to find a second competency cannot silently drop the first.
--}}
@once
@push('scripts')
<script>
document.addEventListener('input', e => {
    const box = e.target.closest('[data-checklist-filter]');
    if (!box) return;

    const list = document.getElementById(box.dataset.checklistFilter);
    if (!list) return;

    const q = box.value.trim().toLowerCase();
    let shown = 0;
    list.querySelectorAll('label').forEach(row => {
        const hit = !q || row.textContent.toLowerCase().includes(q);
        row.style.display = hit ? '' : 'none';
        if (hit) shown++;
    });

    const empty = list.querySelector('[data-checklist-empty]');
    if (empty) empty.style.display = shown ? 'none' : '';
});
</script>
@endpush
@endonce
