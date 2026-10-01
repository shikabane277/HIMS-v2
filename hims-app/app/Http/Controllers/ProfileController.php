<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AuditTrail;
use App\Support\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Generate temporary TOTP secret for setup.
     */
    public function setupTotp(Request $request, TotpService $totpService)
    {
        $secret = $totpService->generateSecret(16);
        $request->session()->put('totp_setup_secret', $secret);

        $otpauth = $totpService->getOtpAuthUri(
            config('app.name', 'HIMS Hospital'),
            $request->user()->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'otpauth' => $otpauth,
        ]);
    }

    /**
     * Confirm 6-digit code and bind TOTP secret to user.
     */
    public function confirmTotp(Request $request, TotpService $totpService): RedirectResponse
    {
        $request->validate([
            'totp_code' => ['required', 'string', 'size:6'],
            'totp_secret' => ['required', 'string'],
        ]);

        $secret = $request->input('totp_secret');
        $code = $request->input('totp_code');

        if (! $totpService->verify($secret, $code)) {
            return back()->with('error', 'Invalid 6-digit code. Please verify the code in your authenticator app.');
        }

        $user = $request->user();
        $user->totp_secret = $secret;
        $user->save();

        AuditTrail::record('totp_2fa_enabled', 'users', (string) $user->id);

        return back()->with('success', 'Authenticator App (TOTP) successfully enabled! You can now use your app for two-factor verification.');
    }

    /**
     * Disable TOTP authentication.
     */
    public function disableTotp(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->totp_secret = null;
        $user->save();

        AuditTrail::record('totp_2fa_disabled', 'users', (string) $user->id);

        return back()->with('success', 'Authenticator App 2FA has been disabled. Login will fall back to email verification.');
    }
}
