<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->warnIfNoMailTransport();

        // A broken or unreachable mail transport throws out of the broker, which
        // would surface as a 500 on an unauthenticated page. Catch it so the user
        // gets an actionable message and the cause lands in the log instead.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            // Report the mailer actually in use — reading mail.mailers.smtp.*
            // would log the wrong host whenever a preset (gmail/outlook/yahoo)
            // is active, and send whoever debugs this down the wrong path.
            $mailer = (string) config('mail.default');

            Log::error('Password reset email could not be sent.', [
                'mail.default' => $mailer,
                'mail.host' => config("mail.mailers.{$mailer}.host"),
                'exception' => $e->getMessage(),
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'We could not send the reset email right now. '
                        .'Please try again shortly, or contact your HIMS administrator.',
                ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __($status));
    }

    /**
     * Record a server-side warning when this deployment has no real mail
     * transport, so a misconfiguration surfaces in the logs for an operator.
     *
     * Deliberately NOT shown to the visitor: this endpoint is unauthenticated,
     * and naming the mailer would disclose deployment configuration to anyone
     * who can type an email address.
     */
    private function warnIfNoMailTransport(): void
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            Log::warning(
                'Password reset requested but no mail transport is configured; '
                .'the reset link will not be delivered. Set MAIL_MAILER to smtp '
                .'(or another real transport) and run php artisan config:clear.',
                ['mail.default' => $mailer]
            );
        }
    }
}
