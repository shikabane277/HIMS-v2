<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Two-Factor Authentication (Authenticator App / TOTP)') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Enhance your account security using a time-based one-time password (TOTP) generator like Google Authenticator, Microsoft Authenticator, or 1Password.') }}
        </p>
    </header>

    @if(session('success'))
        <div class="mt-4 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-md">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mt-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-md">
            {{ session('error') }}
        </div>
    @endif

    <div class="mt-6">
        @if(auth()->user()->totp_secret)
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            ✓ Active
                        </span>
                        <span class="text-sm font-semibold text-emerald-900">Authenticator App 2FA is Enabled</span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-emerald-700">
                    Your account is protected. You can enter 6-digit codes generated from your authenticator app at login, or fall back to your email verification code.
                </p>
            </div>

            <form method="POST" action="{{ route('profile.totp.disable') }}" class="mt-4">
                @csrf
                <div class="max-w-xs">
                    <x-input-label for="totp_disable_password" :value="__('Confirm Password to Disable')" />
                    <x-text-input id="totp_disable_password" name="password" type="password" class="mt-1 block w-full text-sm" placeholder="Current Password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <button type="submit" class="mt-3 inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition" onclick="return confirm('Disable authenticator app 2FA? You will revert to email codes.')">
                    {{ __('Disable Authenticator App') }}
                </button>
            </form>
        @else
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-sm text-gray-700">
                    Authenticator app 2FA is currently not configured for your account. Login verification is delivered via email codes.
                </p>
                <button type="button" id="startTotpSetupBtn" class="mt-3 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition" onclick="startTotpSetup()">
                    {{ __('Setup Authenticator App') }}
                </button>
            </div>

            <div id="totpSetupPanel" class="mt-4 p-5 bg-white border border-indigo-200 rounded-lg shadow-sm" style="display:none">
                <h3 class="text-sm font-bold text-gray-900 mb-2">Step 1: Add to Authenticator App</h3>
                <p class="text-xs text-gray-600 mb-3">
                    In your authenticator app (Google Authenticator, Microsoft Authenticator, Duo, or 1Password), add a new account manually with this setup key:
                </p>
                <div class="p-3 bg-gray-100 rounded border border-gray-300 font-mono text-base font-bold text-center tracking-widest text-indigo-700 select-all" id="totpSecretDisplay">
                    Generating...
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Or open directly with your app protocol: <a id="totpUriLink" href="#" class="text-indigo-600 underline text-xs break-all">otpauth link</a>
                </p>

                <h3 class="text-sm font-bold text-gray-900 mt-5 mb-2">Step 2: Enter 6-Digit Code to Confirm</h3>
                <form method="POST" action="{{ route('profile.totp.confirm') }}">
                    @csrf
                    <input type="hidden" name="totp_secret" id="totpSecretInput">
                    <div class="max-w-xs">
                        <x-input-label for="totp_code" :value="__('6-Digit Authenticator Code')" />
                        <x-text-input id="totp_code" name="totp_code" type="text" maxlength="6" class="mt-1 block w-full text-center text-xl font-mono font-bold tracking-widest" placeholder="123456" required />
                        <x-input-error :messages="$errors->get('totp_code')" class="mt-2" />
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">
                            {{ __('Verify & Enable 2FA') }}
                        </button>
                        <button type="button" class="text-xs text-gray-500 underline" onclick="document.getElementById('totpSetupPanel').style.display='none'">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <script>
                function startTotpSetup() {
                    const panel = document.getElementById('totpSetupPanel');
                    panel.style.display = 'block';
                    fetch('{{ route('profile.totp.setup') }}')
                        .then(r => r.json())
                        .then(data => {
                            document.getElementById('totpSecretDisplay').textContent = data.secret;
                            document.getElementById('totpSecretInput').value = data.secret;
                            const uriLink = document.getElementById('totpUriLink');
                            uriLink.href = data.otpauth;
                            uriLink.textContent = data.otpauth;
                        })
                        .catch(err => {
                            alert('Unable to generate TOTP secret. Please try again.');
                        });
                }
            </script>
        @endif
    </div>
</section>
