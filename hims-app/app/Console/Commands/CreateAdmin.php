<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hims:create-admin
        {--email= : Admin email address}
        {--name= : Admin display name}
        {--password= : Admin password (prompted if omitted)}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new administrator account with linked employee profile';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Admin email address');
        $name  = $this->option('name')  ?? $this->ask('Admin display name', 'HIMS Administrator');

        $password = $this->option('password')
            ?? $this->secret('Admin password (min 8 characters)');

        // ── Validate inputs ────────────────────────────────────────
        $validator = Validator::make(
            compact('email', 'name', 'password'),
            [
                'email'    => ['required', 'email', 'unique:users,email'],
                'name'     => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // ── Confirm ────────────────────────────────────────────────
        if (! $this->option('force')) {
            $this->table(['Field', 'Value'], [
                ['Email', $email],
                ['Name',  $name],
                ['Role',  'admin'],
            ]);

            if (! $this->confirm('Create this administrator account?')) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        // ── Ensure Administration department and role exist ────────
        $adminDept = DB::table('departments')
            ->where('department_code', 'ADM')
            ->first();

        if (! $adminDept) {
            $adminDeptId = (string) Str::uuid();
            DB::table('departments')->insert([
                'department_id'   => $adminDeptId,
                'name'            => 'Hospital Administration',
                'department_code' => 'ADM',
                'is_clinical'     => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } else {
            $adminDeptId = $adminDept->department_id;
        }

        $adminRole = DB::table('roles')
            ->where('role_slug', 'system_admin')
            ->first();

        if (! $adminRole) {
            $adminRoleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'role_id'       => $adminRoleId,
                'role_name'     => 'System Administrator',
                'role_slug'     => 'system_admin',
                'department_id' => $adminDeptId,
                'is_clinical'   => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } else {
            $adminRoleId = $adminRole->role_id;
        }

        // ── Generate next employee code ────────────────────────────
        $lastCode = DB::table('employees')
            ->where('employee_code', 'LIKE', 'EMP-%')
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $nextNum = $lastCode
            ? ((int) substr($lastCode, 4)) + 1
            : 1;

        $employeeCode = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // ── Create employee + user in a transaction ────────────────
        DB::transaction(function () use ($email, $name, $password, $adminDeptId, $adminRoleId, $employeeCode) {
            $employeeId = (string) Str::uuid();

            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? 'Administrator';

            DB::table('employees')->insert([
                'employee_id'       => $employeeId,
                'employee_code'     => $employeeCode,
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'email'             => $email,
                'department_id'     => $adminDeptId,
                'role_id'           => $adminRoleId,
                'position_title'    => 'System Administrator',
                'hire_date'         => now()->toDateString(),
                'employment_status' => 'active',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            DB::table('users')->insert([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make($password),
                'role'              => 'admin',
                'employee_id'       => $employeeId,
                'email_verified_at' => now(),
                'must_change_password' => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        $this->info("✅ Admin account created successfully.");
        $this->line("   Email: {$email}");
        $this->line("   Employee Code: {$employeeCode}");
        $this->line("   The user will be forced to change their password on first login.");

        return self::SUCCESS;
    }
}
