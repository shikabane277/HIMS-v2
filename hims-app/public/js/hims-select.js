/**
 * HIMS Animated Custom Select Dropdowns
 * Replaces native HTML <select> elements with sleek, animated dropdown menus
 * while keeping 100% form compatibility and event bubbling.
 */
(function() {
    'use strict';

    function enhanceSelect(select) {
        if (!select || select.dataset.customized === 'true') return;
        if (select.hasAttribute('multiple')) return;
        if (select.dataset.customSelect === 'false' || select.classList.contains('hims-select-native')) return;

        select.dataset.customized = 'true';

        // Capture original inline styles before hiding select
        const originalWidth = select.style.width;
        const originalPadding = select.style.padding;
        const originalFontSize = select.style.fontSize;

        // Keep native select in DOM for forms, accessibility & validation
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        select.style.position = 'absolute';
        select.style.opacity = '0';
        select.style.pointerEvents = 'none';
        select.style.width = '1px';
        select.style.height = '1px';
        select.style.margin = '0';
        select.style.padding = '0';
        select.style.border = '0';
        select.style.clip = 'rect(0,0,0,0)';

        const wrapper = document.createElement('div');
        wrapper.className = 'hims-select-wrapper';

        // Preserve width if explicitly set inline (e.g. style="width:140px")
        if (originalWidth && originalWidth !== '100%') {
            wrapper.style.width = originalWidth;
            wrapper.style.display = 'inline-block';
        } else {
            wrapper.style.width = '100%';
            wrapper.style.display = 'block';
        }

        const trigger = document.createElement('div');
        trigger.className = 'hims-select-trigger';
        if (originalPadding) trigger.style.padding = originalPadding;
        if (originalFontSize) trigger.style.fontSize = originalFontSize;
        trigger.setAttribute('tabindex', select.disabled ? '-1' : '0');
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        if (select.disabled) {
            trigger.classList.add('disabled');
        }

        const triggerText = document.createElement('span');
        triggerText.className = 'hims-select-trigger-text';

        const arrow = document.createElement('span');
        arrow.className = 'hims-select-arrow';
        arrow.innerHTML = '<i class="bi bi-chevron-down"></i>';

        trigger.appendChild(triggerText);
        trigger.appendChild(arrow);

        const menu = document.createElement('div');
        menu.className = 'hims-select-menu';
        menu.setAttribute('role', 'listbox');

        function renderOptions() {
            menu.innerHTML = '';
            const options = Array.from(select.options);
            const selectedOption = select.options[select.selectedIndex];

            function updateTriggerContent(opt, isPlaceholder) {
                triggerText.innerHTML = '';
                if (opt && opt.dataset.icon) {
                    const iconEl = document.createElement('i');
                    iconEl.className = opt.dataset.icon + ' select-option-icon';
                    triggerText.appendChild(iconEl);
                    const labelEl = document.createElement('span');
                    labelEl.textContent = opt.text;
                    triggerText.appendChild(labelEl);
                } else {
                    triggerText.textContent = opt ? opt.text : '— Select —';
                }
                if (isPlaceholder) {
                    triggerText.classList.add('placeholder');
                } else {
                    triggerText.classList.remove('placeholder');
                }
            }

            if (selectedOption && selectedOption.value !== '') {
                updateTriggerContent(selectedOption, false);
            } else if (selectedOption) {
                updateTriggerContent(selectedOption, true);
            } else {
                updateTriggerContent(null, true);
            }

            options.forEach((opt, idx) => {
                const optEl = document.createElement('div');
                optEl.className = 'hims-select-option';
                if (opt.selected) optEl.classList.add('selected');
                if (!opt.value) optEl.classList.add('is-placeholder');
                if (opt.disabled) optEl.classList.add('disabled');
                optEl.setAttribute('role', 'option');
                optEl.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
                optEl.dataset.value = opt.value;
                optEl.dataset.index = idx;

                const textSpan = document.createElement('span');
                textSpan.className = 'option-label';
                if (opt.dataset.icon) {
                    const iconEl = document.createElement('i');
                    iconEl.className = opt.dataset.icon + ' select-option-icon';
                    textSpan.appendChild(iconEl);
                    const labelEl = document.createElement('span');
                    labelEl.textContent = opt.text;
                    textSpan.appendChild(labelEl);
                } else {
                    textSpan.textContent = opt.text;
                }

                const checkSpan = document.createElement('span');
                checkSpan.className = 'option-check';
                checkSpan.innerHTML = '<i class="bi bi-check2"></i>';

                optEl.appendChild(textSpan);
                optEl.appendChild(checkSpan);

                optEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (opt.disabled) return;
                    if (select.value !== opt.value) {
                        select.value = opt.value;
                        select.selectedIndex = idx;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        select.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    renderOptions();
                    close();
                    trigger.focus();
                });

                menu.appendChild(optEl);
            });
        }

        function open() {
            if (select.disabled) return;

            // Close other open selects
            document.querySelectorAll('.hims-select-wrapper.open').forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove('open');
                    w.style.removeProperty('z-index');
                    const t = w.querySelector('.hims-select-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                    const parentCard = w.closest('.hims-card, .card');
                    if (parentCard) parentCard.style.removeProperty('z-index');
                }
            });

            // Flip upward if near bottom of window
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < 230 && rect.top > 230) {
                menu.classList.add('opens-up');
            } else {
                menu.classList.remove('opens-up');
            }

            wrapper.classList.add('open');
            wrapper.style.zIndex = '99999';
            const card = wrapper.closest('.hims-card, .card');
            if (card) {
                card.style.overflow = 'visible';
                card.style.position = 'relative';
                card.style.zIndex = '99998';
            }
            const cardBody = wrapper.closest('.card-body');
            if (cardBody) {
                cardBody.style.overflow = 'visible';
            }
            trigger.setAttribute('aria-expanded', 'true');

            // Scroll selected option into view inside the dropdown menu
            const selectedOptEl = menu.querySelector('.hims-select-option.selected');
            if (selectedOptEl) {
                selectedOptEl.scrollIntoView({ block: 'nearest' });
            }
        }

        function close() {
            wrapper.classList.remove('open');
            wrapper.style.removeProperty('z-index');
            const card = wrapper.closest('.hims-card, .card');
            if (card) {
                card.style.removeProperty('z-index');
            }
            trigger.setAttribute('aria-expanded', 'false');
        }

        function toggle() {
            if (wrapper.classList.contains('open')) {
                close();
            } else {
                open();
            }
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggle();
        });

        // Keyboard navigation
        trigger.addEventListener('keydown', (e) => {
            if (select.disabled) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!wrapper.classList.contains('open')) {
                    open();
                    return;
                }
                const options = Array.from(menu.querySelectorAll('.hims-select-option:not(.disabled)'));
                if (!options.length) return;
                const curIdx = options.findIndex(o => o.classList.contains('focused') || o.classList.contains('selected'));
                let nextIdx = e.key === 'ArrowDown' ? curIdx + 1 : curIdx - 1;
                if (nextIdx >= options.length) nextIdx = 0;
                if (nextIdx < 0) nextIdx = options.length - 1;

                options.forEach(o => o.classList.remove('focused'));
                options[nextIdx].classList.add('focused');
                options[nextIdx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!wrapper.classList.contains('open')) {
                    open();
                } else {
                    const focused = menu.querySelector('.hims-select-option.focused') || menu.querySelector('.hims-select-option.selected');
                    if (focused) focused.click();
                    else close();
                }
            } else if (e.key === 'Escape') {
                close();
            } else if (e.key === 'Tab') {
                close();
            }
        });

        // Clicking associated label focuses our custom trigger
        select.addEventListener('focus', () => {
            trigger.focus();
        });

        // Sync when native select changes programmatically
        select.addEventListener('change', renderOptions);

        // Sync on form reset
        if (select.form) {
            select.form.addEventListener('reset', () => {
                setTimeout(renderOptions, 10);
            });
        }

        renderOptions();

        // Wrap around select
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);
    }

    // Close open dropdowns when clicking anywhere outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.hims-select-wrapper')) {
            document.querySelectorAll('.hims-select-wrapper.open').forEach(w => {
                w.classList.remove('open');
                const t = w.querySelector('.hims-select-trigger');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
        }
    });

    window.initCustomSelects = function(root = document) {
        root.querySelectorAll('select.hims-select, select.hims-input').forEach(enhanceSelect);
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.initCustomSelects());
    } else {
        window.initCustomSelects();
    }

    // MutationObserver to automatically enhance newly inserted selects (e.g. dynamic elements/modals)
    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.matches && (node.matches('select.hims-select') || node.matches('select.hims-input'))) {
                        enhanceSelect(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('select.hims-select, select.hims-input').forEach(enhanceSelect);
                    }
                }
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
