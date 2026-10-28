{{--
    Hospital Record Access Privacy Curtain (Blur & Unlock)
    Provides styles and client-side unlock scripts for sensitive workforce tabs and tables.
--}}
<style>
.hims-sensitive-wrapper {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    min-height: 180px;
}

.hims-sensitive-records {
    transition: filter 0.25s ease, opacity 0.25s ease;
}

.hims-sensitive-records.is-blurred {
    filter: blur(9px) grayscale(25%);
    user-select: none !important;
    pointer-events: none !important;
    opacity: 0.5;
}

.hims-sensitive-overlay {
    position: absolute;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    background: rgba(248, 250, 252, 0.75);
    backdrop-filter: blur(4px);
    padding: 36px 20px;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}

.hims-sensitive-overlay.is-hidden {
    display: none !important;
}

.hims-sensitive-card {
    position: sticky;
    top: 90px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.12), 0 8px 12px -6px rgba(0, 0, 0, 0.08);
    padding: 24px 28px;
    max-width: 440px;
    width: 100%;
    text-align: center;
    animation: himsFadeInUp 0.25s ease;
}

.hims-sensitive-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #ecfdf5;
    color: #059669;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 12px;
}

.hims-sensitive-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 6px;
}

.hims-sensitive-card p {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
    margin: 0 0 16px;
}

.hims-sensitive-input-group {
    display: flex;
    gap: 8px;
}

.hims-sensitive-input-group input {
    flex: 1;
    padding: 9px 13px;
    font-size: 13.5px;
    border-radius: 8px;
    border: 1.5px solid #cbd5e1;
    outline: none;
    transition: border-color 0.2s;
}

.hims-sensitive-input-group input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

.hims-sensitive-btn {
    padding: 9px 18px;
    font-size: 13.5px;
    font-weight: 600;
    background: #059669;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
    white-space: nowrap;
}

.hims-sensitive-btn:hover {
    background: #047857;
}

.hims-sensitive-error {
    font-size: 12.5px;
    color: #dc2626;
    margin-top: 6px;
    text-align: left;
    display: none;
}

@keyframes himsFadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
function himsUnlockSensitiveRecords(event, form) {
    event.preventDefault();
    var input = form.querySelector('input[name="password"]');
    var btn = form.querySelector('button[type="submit"]');
    var err = form.querySelector('.hims-sensitive-error');
    var password = input ? input.value : '';

    if (!password) {
        if (input) input.focus();
        return;
    }

    btn.disabled = true;
    var originalBtnText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Verifying...';
    if (err) err.style.display = 'none';

    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var csrf = metaCsrf ? metaCsrf.content : '';

    fetch('{{ route('record-access.unlock') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({ password: password })
    })
    .then(function(res) {
        return res.json().then(function(data) {
            return { ok: res.ok, data: data };
        });
    })
    .then(function(result) {
        if (result.ok && result.data.success) {
            // Unblur all sensitive sections on current page
            document.querySelectorAll('.hims-sensitive-records').forEach(function(el) {
                el.classList.remove('is-blurred');
            });
            document.querySelectorAll('.hims-sensitive-overlay').forEach(function(el) {
                el.classList.add('is-hidden');
            });
            document.querySelectorAll('.hims-sidebar-lock-icon').forEach(function(el) {
                el.style.display = 'none';
            });

            // Update topbar indicator if present
            var lockBtn = document.getElementById('himsRecordLockToggle');
            if (lockBtn) {
                lockBtn.innerHTML = '<i class="bi bi-unlock-fill"></i> Records Unlocked';
                lockBtn.className = 'btn-hims btn-hims-ghost btn-sm text-success';
                lockBtn.setAttribute('title', 'Sensitive records are unlocked. Click to re-lock.');
                lockBtn.onclick = himsLockSensitiveRecords;
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
            if (err) {
                err.textContent = (result.data && result.data.message) ? result.data.message : 'Incorrect password.';
                err.style.display = 'block';
            }
            if (input) {
                input.focus();
                input.select();
            }
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = originalBtnText;
        if (err) {
            err.textContent = 'Connection error. Please try again.';
            err.style.display = 'block';
        }
    });
}

function himsLockSensitiveRecords() {
    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var csrf = metaCsrf ? metaCsrf.content : '';

    fetch('{{ route('record-access.lock') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf
        }
    }).then(function() {
        window.location.reload();
    });
}

function himsTogglePwVisibility(btn) {
    var wrapper = btn ? btn.parentElement : null;
    var input = wrapper ? wrapper.querySelector('input') : null;
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}
</script>
