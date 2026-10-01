<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email'))->first();

        // Validate password FIRST — checking lockout before password validation
        // would leak whether the email exists (timing + different error message).
        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user) {
                $attempts = (int) ($user->failed_login_attempts ?? 0) + 1;
                $updateData = [
                    'failed_login_attempts' => $attempts,
                    'updated_at' => now(),
                ];

                // Lock after 5 consecutive failures — no isset() guard needed,
                // the migration guarantees these columns exist.
                if ($attempts >= 5) {
                    $updateData['locked_until'] = now()->addMinutes(15);
                }

                DB::table('users')->where('id', $user->id)->update($updateData);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Password is correct — check if account is deactivated.
        if (isset($user->is_active) && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your system administrator.',
            ]);
        }

        // Check if the account is locked.
        // This prevents locked accounts from logging in even with correct creds,
        // while using the same generic error so attackers can't distinguish
        // "locked" from "wrong password".
        if ($user->locked_until && now()->lt($user->locked_until)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Successful login — reset counters
        DB::table('users')->where('id', $user->id)->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'updated_at' => now(),
        ]);

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
