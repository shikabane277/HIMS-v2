{{--
    Generic modal controller. Included (via @once) by every partial that ships a
    .hims-modal-backdrop, so a page with four modals still gets one copy of this.

    Everything is delegated off document rather than bound per modal, which means
    a modal appended to the page later still works and no modal needs its own
    listener block. The contract is markup-only:

        <div class="hims-modal-backdrop" id="thing">…</div>   the panel
        [data-modal-open="thing"]                             opens it
        [data-modal-dismiss] inside the backdrop              closes it

    Escape and a click on the backdrop itself also close. window.himsModal.open()
    / .close() drive it from other scripts — the review-cycle editor calls open()
    after re-pointing its form at the row that was clicked.
--}}
@once
@push('scripts')
<script>
window.himsModal = (function () {
    let lastTrigger = null;

    const el = t => typeof t === 'string' ? document.getElementById(t) : t;

    function open(target) {
        const backdrop = el(target);
        if (!backdrop) return;
        backdrop.classList.add('open');
        // Next frame, so the browser has a painted start state to animate from.
        requestAnimationFrame(() => backdrop.classList.add('shown'));
        document.body.style.overflow = 'hidden';
        const first = backdrop.querySelector('input:not([type=hidden]), select, textarea');
        if (first) first.focus();
    }

    function close(target) {
        const backdrop = el(target);
        if (!backdrop) return;
        backdrop.classList.remove('shown');
        document.body.style.overflow = '';
        setTimeout(() => backdrop.classList.remove('open'), 200);
        if (lastTrigger) { lastTrigger.focus(); lastTrigger = null; }
    }

    document.addEventListener('click', e => {
        const trigger = e.target.closest('[data-modal-open]');
        if (trigger) {
            lastTrigger = trigger;
            open(trigger.dataset.modalOpen);
            return;
        }
        const dismiss = e.target.closest('[data-modal-dismiss]');
        if (dismiss) {
            close(dismiss.closest('.hims-modal-backdrop'));
            return;
        }
        // The backdrop is a full-viewport dismissal hit area; the panel sitting
        // on top of it is not, hence the exact-target test.
        if (e.target.classList.contains('hims-modal-backdrop')) close(e.target);
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const shown = document.querySelector('.hims-modal-backdrop.open');
        if (shown) close(shown);
    });

    return { open, close };
})();
</script>
@endpush
@endonce
