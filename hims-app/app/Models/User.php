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

#[Fillable(['name', 'email', 'password', 'role', 'employee_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
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
            'password' => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------
     | Role helpers
     |
     | users.role is one of: admin | hr_manager | supervisor | staff
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
