<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Every login account needs a linked employees row: a dozen write paths
     * route auth()->user()->employee_id into NOT NULL char(36) FK columns
     * (competency_assessments.assessed_by, recognition_posts.author_id,
     * course_enrollments.employee_id, ...). Accounts created before this
     * migration have employee_id = NULL, so those inserts fail.
     *
     * This backfill is additive — it never deletes or reassigns an existing
     * link. On a fresh install the users table is still empty when migrations
     * run, so DatabaseSeeder does the equivalent linking itself.
     */
    public function up(): void
    {
        $this->linkByEmail();
        $this->createProfilesForRemainingUsers();
        $this->ensureAnAdminExists();
    }

    /**
     * Cheapest, most accurate link: the login email matches an employee email.
     */
    private function linkByEmail(): void
    {
        $unlinked = DB::table('users')->whereNull('employee_id')->get(['id', 'email']);

        foreach ($unlinked as $user) {
            $employeeId = DB::table('employees')->where('email', $user->email)->value('employee_id');

            if ($employeeId && ! $this->employeeIsTaken($employeeId)) {
                DB::table('users')->where('id', $user->id)->update([
                    'employee_id' => $employeeId,
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    /**
     * Anyone still unlinked (e.g. the seeded admin@hospital.ph, which has no
     * matching employee record) gets a real employee profile created for them
     * under an Administration department.
     */
    private function createProfilesForRemainingUsers(): void
    {
        $remaining = DB::table('users')->whereNull('employee_id')->orderBy('id')->get(['id', 'name', 'email', 'created_at']);

        if ($remaining->isEmpty()) {
            return;
        }

        $departmentId = $this->ensureDepartment('Hospital Administration', 'ADM');
        $roleId       = $this->ensureRole('System Administrator', 'system_admin', $departmentId);

        foreach ($remaining as $user) {
            [$first, $last] = $this->splitName($user->name);

            $employeeId = (string) Str::uuid();

            DB::table('employees')->insert([
                'employee_id'       => $employeeId,
                'employee_code'     => $this->nextEmployeeCode(),
                'first_name'        => $first,
                'last_name'         => $last,
                'email'             => $user->email,
                'department_id'     => $departmentId,
                'role_id'           => $roleId,
                'position_title'    => 'System Administrator',
                'hire_date'         => $user->created_at ? substr((string) $user->created_at, 0, 10) : now()->toDateString(),
                'employment_status' => 'active',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'employee_id' => $employeeId,
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * A system with no admin cannot manage users or departments at all, so the
     * seeded administrator (or failing that the oldest account) is promoted.
     */
    private function ensureAnAdminExists(): void
    {
        DB::table('users')->where('email', 'admin@hospital.ph')->update([
            'role'       => 'admin',
            'updated_at' => now(),
        ]);

        if (DB::table('users')->where('role', 'admin')->exists()) {
            return;
        }

        $oldest = DB::table('users')->orderBy('id')->value('id');

        if ($oldest) {
            DB::table('users')->where('id', $oldest)->update([
                'role'       => 'admin',
                'updated_at' => now(),
            ]);
        }
    }

    private function employeeIsTaken(string $employeeId): bool
    {
        return DB::table('users')->where('employee_id', $employeeId)->exists();
    }

    private function ensureDepartment(string $name, string $code): string
    {
        $existing = DB::table('departments')->where('name', $name)->value('department_id');

        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();

        DB::table('departments')->insert([
            'department_id'   => $id,
            'name'            => $name,
            'department_code' => DB::table('departments')->where('department_code', $code)->exists() ? null : $code,
            'is_clinical'     => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $id;
    }

    private function ensureRole(string $name, string $slug, string $departmentId): string
    {
        $existing = DB::table('roles')->where('role_slug', $slug)->value('role_id');

        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();

        DB::table('roles')->insert([
            'role_id'       => $id,
            'role_name'     => $name,
            'role_slug'     => $slug,
            'department_id' => $departmentId,
            'is_clinical'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $id;
    }

    /**
     * employees.employee_code is unique; keep scanning until a free slot.
     */
    private function nextEmployeeCode(): string
    {
        $n = DB::table('employees')->count() + 1;

        do {
            $code = 'EMP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (DB::table('employees')->where('employee_code', $code)->exists());

        return $code;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $parts) {
            return ['System', 'User'];
        }

        if (count($parts) === 1) {
            return [$parts[0], '-'];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    /**
     * Data backfill — nothing to reverse. Dropping the links would only
     * re-break the write paths this migration exists to fix.
     */
    public function down(): void
    {
        //
    }
};
