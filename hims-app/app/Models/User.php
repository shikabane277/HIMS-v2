<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'access_expires_at', 'employee_id', 'email_verified_at', 'two_factor_code', 'two_factor_expires_at', 'totp_secret'])]
#[Hidden(['password', 'remember_token', 'two_factor_code', 'totp_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'access_expires_at' => 'datetime',
        ];
    }

    /**
     * Clear active two-factor challenge code.
     */
    public function resetTwoFactorCode(): void
    {
        $this->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);
    }

    /* ---------------------------------------------------------------
     | Role helpers
     |
     | users.role is one of App\Support\Roles::ALL:
     | admin | hr_manager | training_officer | supervisor | staff | trainee
     | ---------------------------------------------------------------
     */

    /**
     * True when the user's role matches any of the given roles.
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHrManager(): bool
    {
        return $this->role === 'hr_manager';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isTrainingOfficer(): bool
    {
        return $this->role === 'training_officer';
    }

    public function isTrainee(): bool
    {
        return $this->role === 'trainee';
    }

    /** Self-service accounts: staff and trainees see only their own records. */
    public function isSelfServiceOnly(): bool
    {
        return $this->hasRole('staff', 'trainee');
    }

    /** A time-limited account (e.g. trainee) whose end date has passed. */
    public function accessHasExpired(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    /**
     * Hospital-wide view of learning, training and compliance records:
     * admin, HR and the Training Officer (who sees nothing else hospital-wide).
     */
    public function seesAllLearners(): bool
    {
        return $this->hasRole('admin', 'hr_manager', 'training_officer');
    }

    public function canCompleteReviews(): bool
    {
        return $this->hasRole('admin', 'hr_manager', 'supervisor');
    }

    /**
     * Admin and HR see the whole organisation; everyone else is scoped.
     */
    public function seesWholeOrganisation(): bool
    {
        return $this->hasRole('admin', 'hr_manager');
    }

    /**
     * Request-scoped cache for departmentId(). Not an attribute, so it never
     * gets written back to the users table on save.
     */
    protected ?string $resolvedDepartmentId = null;

    protected bool $departmentIdResolved = false;

    /**
     * The department_id this user belongs to via their linked employee row,
     * or null when the user is not linked to an employee.
     */
    public function departmentId(): ?string
    {
        if ($this->departmentIdResolved) {
            return $this->resolvedDepartmentId;
        }

        $this->departmentIdResolved = true;

        if ($this->employee_id) {
            $this->resolvedDepartmentId = DB::table('employees')
                ->where('employee_id', $this->employee_id)
                ->value('department_id');
        }

        return $this->resolvedDepartmentId;
    }
}
