{{--
    Sensitive Records Privacy Overlay
    Renders over blurred record tables/tabs when locked.
--}}
<div class="hims-sensitive-overlay">
    <div class="hims-sensitive-card">
        <div class="hims-sensitive-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h4>Confidential Records Protected</h4>
        <p>{{ $slot ?? 'This section contains sensitive employee records. Enter the Record Access Password to unblur.' }}</p>
        <form class="hims-sensitive-form" onsubmit="himsUnlockSensitiveRecords(event, this)">
            <div class="hims-sensitive-input-group">
                <div style="position:relative;flex:1;display:flex;align-items:center">
                    <input type="password" name="password" placeholder="Enter record password..." autocomplete="current-password" required style="width:100%;padding-right:36px">
                    <button type="button" onclick="himsTogglePwVisibility(this)" style="position:absolute;right:10px;background:none;border:none;color:#94a3b8;cursor:pointer;padding:0;display:flex;align-items:center" title="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <button type="submit" class="hims-sensitive-btn">
                    <i class="bi bi-unlock-fill"></i> Unlock
                </button>
            </div>
            <div class="hims-sensitive-error"></div>
        </form>
    </div>
</div>
