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
        $user = User::where('email', $this->string('email'))->first();

        if ($user && isset($user->locked_until) && $user->locked_until && now()->lt($user->locked_until)) {
            throw ValidationException::withMessages([
                'email' => 'This account is locked due to multiple failed login attempts. Please contact an administrator to unlock your account.',
            ]);
        }

        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user) {
                $attempts = (int) ($user->failed_login_attempts ?? 0) + 1;
                $updateData = ['updated_at' => now()];

                // Only write columns if they exist in the schema
                if (isset($user->failed_login_attempts)) {
                    $updateData['failed_login_attempts'] = $attempts;
                }
                if (isset($user->locked_until) && $attempts >= 5) {
                    $updateData['locked_until'] = now()->addMinutes(15);
                }

                if (count($updateData) > 1) {
                    DB::table('users')->where('id', $user->id)->update($updateData);
                }
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user && isset($user->failed_login_attempts)) {
            DB::table('users')->where('id', $user->id)->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'updated_at' => now(),
            ]);
        }

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
