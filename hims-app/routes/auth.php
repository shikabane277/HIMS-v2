<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorAuthenticatedSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Public self-registration is deliberately DISABLED
    |--------------------------------------------------------------------------
    | This is an internal hospital HR system. A self-registered account would
    | land with no employee_id and the users.role DB default of 'staff', which
    | is a stranger holding a login to workforce data.
    |
    | Accounts are provisioned instead by an admin through /users
    | (UserController, gated by 'role:admin'), which assigns both the role and
    | the linked employee profile explicitly.
    |
    | The Breeze RegisteredUserController and auth/register.blade.php are left
    | in place, unrouted, so the scaffolding stays intact if onboarding ever
    | needs an invitation flow.
    */

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->name('two-factor.show');

    Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->name('two-factor.verify');

    Route::post('two-factor-challenge/resend', [TwoFactorAuthenticatedSessionController::class, 'resend'])
        ->name('two-factor.resend');

    Route::get('two-factor-challenge/cancel', [TwoFactorAuthenticatedSessionController::class, 'destroy'])
        ->name('two-factor.cancel');

    Route::get('2fa', [TwoFactorAuthenticatedSessionController::class, 'create']);
    Route::post('2fa', [TwoFactorAuthenticatedSessionController::class, 'store']);

    /*
     * PASSWORD RESET IS DELIBERATELY ABSENT.
     *
     * There is no forgot-password form, no reset link mail and no password.reset
     * route. A reset link is a self-service path into an account that holds staff
     * records, and its delivery depends on SMTP — the same dependency that was
     * failing open in the 2FA challenge. Recovery is instead an admin action:
     * an administrator sets a temporary password from /users and the account is
     * forced to change it at next login (EnsurePasswordIsChanged).
     *
     * The Breeze controllers (PasswordResetLinkController, NewPasswordController)
     * and their views were deleted with these routes; there is nothing left to
     * re-point at them.
     */
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    /*
     * Reports whether the session is still inside the idle window. It does NOT
     * refresh hims_last_activity: the old version did, which made this endpoint a
     * way to hold a forgotten session open indefinitely by pinging it. Whether a
     * request counts as activity is EnforceSessionTimeout's call, and it is based
     * on the request arriving, not on anyone asking.
     */
    Route::post('session/ping', function (Request $request) {
        $lastActivity = (int) $request->session()->get('hims_last_activity', 0);
        $timeoutSeconds = max(60, (int) (env('SESSION_IDLE_TIMEOUT') ?: config('session.idle_timeout', 300)) * 60);
        $expired = $lastActivity > 0 && (time() - $lastActivity > $timeoutSeconds);

        return response()->json([
            'status' => $expired ? 'expired' : 'active',
            'expires_in' => $expired ? 0 : max(0, $timeoutSeconds - (time() - $lastActivity)),
            'timestamp' => time(),
        ]);
    })->name('session.ping');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
